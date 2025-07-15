<?php

namespace App\Http\Controllers;

use App\Jobs\PollAccessApiJob;
use App\Models\FormData;
use App\Models\PoolingStatus;
use App\Models\Templates;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class AutodeskController extends Controller
{
    //

    protected $clientObj;

    public function connectionApi(){
        
        $clientId = env('CLIENT_ID');
        $clientSecret = env('CLIENT_SECRET');
        $credentials = base64_encode($clientId . ':' . $clientSecret);
        $client = new Client();

        try {
            $response = $client->post('https://developer.api.autodesk.com/authentication/v2/token', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept' => 'application/json',
                    'Authorization' => 'Basic ' . $credentials, // Add Base64 encoded credentials
                ],
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'scope' => 'data:read data:write bucket:read bucket:create account:read',
                ],
            ]);
            $response_data = json_decode($response->getBody(), true);

            $access_token = $response_data['access_token'];

            $response = $this->getForms($access_token);

            return ["success"=>true,"response"=>$response];
        
        } catch (RequestException $e) {
            return ["success"=>false,"message"=>$e->getMessage()];
        }
    }

    public function retrieveAccessToken()
    {
        $clientId = env('CLIENT_ID');
        $clientSecret = env('CLIENT_SECRET');

        $tokens = $this->getTokensFromFile();
        $callback = array("success"=>false);
      
        if(empty($tokens)){
            $response['message'] = "No Token Setup";
            return $callback;
        }

        $currentTime = time(); 
        $expiresAt = $tokens['expires_in'];

        if ($currentTime >= $expiresAt) {
    
            $refresh_data = [
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $tokens['refresh_token'],
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret
                ]
            ];
          
            try {
                $client = new Client();
                $response = $client->post('https://developer.api.autodesk.com/authentication/v2/token', $refresh_data);
                $response_data = json_decode($response->getBody(), true);
                 $this->saveTokens($response_data['access_token'], $response_data['refresh_token'], $response_data['expires_in']);
          
               
                $accessToken = $response_data['access_token'];
        
                $callback['success'] = true;

                $forms = $this->getForms($tokens['access_token']);
                // $templates = $this->getTemplates($tokens['access_token']);

                $callback['accessToken'] = $accessToken;
                $callback['forms'] = $forms;
                // $callback['templates'] = $templates;

            } catch (\GuzzleHttp\Exception\RequestException $e) {
                $callback['message'] = $e->getMessage();
            }
        }else {
            $callback['success'] = true;
            $callback['accessToken'] = $tokens['access_token'];

            $forms = $this->getForms($tokens['access_token']);
            // $templates = $this->getTemplates($tokens['access_token']);
            
            $callback['forms'] = $forms;
            // $callback['templates'] = $templates;
        }
   
        return $callback;
    }

    public function getTokensFromFile() 
    {
        $path = base_path('public\tokens.json');
        if (file_exists($path)) {
            return json_decode(file_get_contents($path), true);
        }
        return null;
    }


    public  function getAccountIdFromHubs($accessToken) 
    {

        $callback['success'] = false;
        $client = new Client();
        try {
            $response = $client->get('https://developer.api.autodesk.com/project/v1/hubs', [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json'
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            $callback['success'] = true;
            $callback['data'] = $data;

            return $callback;

        } catch (RequestException $e) {
            $message = "";
            if ($e->hasResponse()) {
                $errorBody = $e->getResponse()->getBody()->getContents();
                $message = $errorBody;
            } else {
                $message = $e->getMessage();
            }
            $callback['message'] = $message;
            return $callback;


        }


    }

    public function  getProjectsByHub() {

        $callback = array("success" => false);
        $responseClientApi = $this->settingsObj->getOptionByOptionName("cred_client_api");

        if(!$responseClientApi['success']){
            $callback['message'] = "No Credential Setup";
            return $callback;
        }
        
        $structured   = json_decode($responseClientApi['data']->structured);
        $clientId     = $structured->client_code;
        $clientSecret     = $structured->client_secret;
    

        $responseAccessToken = $this->retrieveAccessToken($clientId,$clientSecret);

        if(!$responseAccessToken['success']){
            return $responseAccessToken;
        }

        $accessToken = $responseAccessToken['accessToken'];
        
        $responseAccountHub = $this->getAccountIdFromHubs($accessToken);

        if(!$responseAccountHub['success']){
            return $responseAccountHub;
        }

        try {
            $hubId = $responseAccountHub['data']['data'][0]['id'];
            $url = "https://developer.api.autodesk.com/project/v1/hubs/{$hubId}/projects";

            $response = $this->clientObj->get($url, [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json'
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            // $option = "";
            // foreach ($data['data'] as $project) {
            //     $option .= "<option value='".$project['id']."'>".$project['attributes']['name']."</option>";
            // }
            $callback['success'] = true;
            $callback['data'] = $data['data'];

            return $callback;

        } catch (\Exception $e) {
            $callback['message'] = "An error occurred: " . $e->getMessage();
        }

        return $callback;

    }

    public function getForms($token)
    {
         $client = new Client();
         
         $projectId = 'b.bfd3a80e-6576-4433-b8fa-758a8812b697';
         try {
            $response = $client->get("https://developer.api.autodesk.com/construction/forms/v1/projects/$projectId/forms", [
                'headers' => [
                    'Authorization' => "Bearer {$token}", // Add Base64 encoded credentials
                ],
                'query' => [
                    'templateId' => 'd1a1299a-57d6-42b2-b6e0-bc39f4ead7ca',
                    
                ],
            ]);
            $response_data = json_decode($response->getBody(), true);
          
            $dataArrys = collect($response_data['data']);
            $resultData = $dataArrys->map(function ($dataArr){
                 return [
                     'id' => $dataArr['id'],
                    'updatedAt' => $dataArr['updatedAt'],
                 ];
            })->toArray();

           $cacheFile = base_path('public/form_snapshot.json');
           
           $previousSnapshot = [];
           
           if (file_exists($cacheFile)) {
              $json = file_get_contents($cacheFile);
              $decoded = json_decode($json, true);

             if (is_array($decoded)) {
               $previousSnapshot = $decoded;
             } else {
               Log::warning('form_snapshot.json contains invalid or null JSON. Resetting snapshot.');
             }
           }

            $hasChanged = count($resultData) !== count($previousSnapshot)
            || array_diff_assoc(array_column($resultData, 'updatedAt', 'id'), array_column($previousSnapshot, 'updatedAt', 'id'));
    
            $arrTest = [];

              foreach ($response_data['data'] as $form) {
                  $testItem = [
                    'id' => $form['id'],    // ✅ extract values properly
                    'template_id' => $form['formTemplate']['id'],
                    'pdfValues' => $form['pdfValues'] ?? ' ',
                ];

                $arrTest[] = $testItem;
               }

                dd($arrTest);

            if($hasChanged){
          
               foreach ($response_data['data'] as $form) {
                    $saveResult = $arrTest([
                        'id' => $form['id'],    // ✅ extract values properly
                        'template_id' => $form['formTemplate']['id'],
                        'pdfValues' => $form['pdfValues'] ?? ' ',
                    ]);

                    if (!$saveResult['success']) {
                        Log::warning($saveResult['message']);
                    } else {
                        Log::info($saveResult['message']);
                    }
                }

                dd($arrTest);
            }
            return ["success"=>true,"response"=>$response_data['data']];
        
        } catch (RequestException $e) {
            return ["success"=>false,"message"=>$e->getMessage()];
        }


    }

    public function saveTokens($access_token, $refresh_token, $expires_in){
        $tokens = [
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
            'expires_in' => time() + $expires_in
        ];

          $path = base_path('public\tokens.json');
        file_put_contents($path, json_encode($tokens));
    }

    public function createAllowUrlAutodesk()
    {
        $scope =  'data:read data:write bucket:read bucket:create account:read';
        $redirectUri = "http://localhost/authorization/{code}";
        $clientId = 'GupHzVv7usXDcDVD6s51w1W5vjBG26eKyVX7vARpDwWGx6wl';
        $clientSecret = 'Y9uv0FavsZVmPOU1fJmwNmnKGc9eoTabBvQ4MP31nY4mAHr8uPl2h8LAm75sr8hX'; 
         
        $structured = array(
                    "client_code"   => $clientId,
                    "client_secret" => $clientSecret,
                );

        Session::put('client_credentials', $structured);

        $authUrl = "https://developer.api.autodesk.com/authentication/v2/authorize?" . http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'scope' => $scope,
        ]);

        return redirect($authUrl);

    }


    public function authorization(Request $request)
    {
        
        $client_cred = Session::get('client_credentials');
       
        $clientId = $client_cred['client_code'];
        $clientSecret = $client_cred['client_secret'];
        $code = $request->
       
        $tokens = $this->getTokensFromFile();
        $data = [
            'form_params' => [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => 'http://autodesk-api-integration.com/dashboard',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ]
        ];

        $client = new Client();

        try {
            
            $response = $client->post('https://developer.api.autodesk.com/authentication/v2/token', $data);
           
            $response_data = json_decode($response->getBody(), true);
             
            $this->saveTokens($response_data['access_token'], $response_data['refresh_token'], $response_data['expires_in']);
            $request = new Request();

            $input = (object)[
                'option_name' => 'cred_client_api',
                'structured' => json_encode($client_cred)
            ];

            Session::flash('alert',[
                'class' => 'alert-success',
                'message' => "Successfully Connected",
            ]);

        } catch (\GuzzleHttp\Exception\RequestException $e) {

            Session::flash('alert',[
                'class' => 'alert-danger',
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function pushFormToPowerBI(array $forms)
    {
      $powerBIUrl = "https://api.powerbi.com/beta/0615ec66-11f8-4f9f-b5ce-c9e4e0d80c37/datasets/a5737e29-5d0c-44f8-a904-c889f3d448ad/rows?experience=power-bi&key=vZ76piv3sTHcBFDcTgiNjtoNCUFACeg6chj0FYzmArxa8HT0jCbOZDPFl0t%2B594CBxqeoUthYgKWjBIbdypa5A%3D%3D";
    
      $rows = [];

      foreach ($forms as $form) {
        // Flatten pdfValues from this single form
        $pdfValues = collect($form['pdfValues'] ?? [])
            ->pluck('value', 'name')
            ->mapWithKeys(fn($value, $key) => [Str::slug($key, '_') => $value])
            ->toArray();

        // Merge with metadata
        $row = array_merge($pdfValues, [
            'form_id' => $form['id'] ?? null,
            'status' => $form['status'] ?? null,
            'createdAt' => $form['createdAt'] ?? now()->toDateTimeString(),
        ]);

        $rows[] = $row;
     }     
    

     
     // Send to Power BI
     try {
        $response = Http::timeout(10)
            ->withHeaders([
                'Content-Type' => 'application/json',
            ])
            ->post($powerBIUrl, $rows);

        if ($response->successful()) {
            return ['success' => true];
        }

        // Log the error response
        Log::error('Power BI push failed', [
            'status' => $response->status(),
            'body' => $response->body()
        ]);

        return [
            'success' => false,
            'error' => 'Power BI push failed',
            'details' => $response->body()
        ];
     } catch (\Exception $e) {
        // Catch any client/network/server errors
        Log::error('Exception while pushing to Power BI', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return [
            'success' => false,
            'error' => 'Exception occurred while sending data to Power BI',
            'message' => $e->getMessage()
        ];
     }
    }

    public function autodeskApiPooling(Request $request)
    {
        try{
            $isPooling = $request->input('isPooling');
        
            if($isPooling == 'true'){
                $poolingStatus = new PoolingStatus([
                 'user_id' => Auth::user()->id,
                 'is_polling' => true,
                 'started_at' => now(),
                 'stopped_at' => null,
                ]);
            }else{
                $poolingStatus = PoolingStatus::where('user_id', Auth::user()->id)
                ->where('is_polling', true)
                ->latest()
                ->first();
                if ($poolingStatus) {
                 $poolingStatus->is_polling = false;
                 $poolingStatus->stopped_at = now();
                } else {
                  return response()->json(['error' => 'No active pooling session found.'], 404);
                }
            }
            $poolingStatus->save();
            PollAccessApiJob::dispatch();
            return response()->json(['success' => true]);
        }catch(\Exception $e){
            Log::info('error on api pooling' . $e);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }



    public function createPowerBIDataset($accessToken)
    {
    
        $datasetDefinition = [
        'name' => 'MyPushDataset',
        'defaultMode' => 'Push',
        'tables' => [
            [
                'name' => 'SalesData',
                'columns' => [
                    ['name' => 'data_json', 'dataType' => 'string'],
                ]
            ]
        ]
     ];

     $client = new Client();

     $response = $client->post('https://api.powerbi.com/v1.0/myorg/datasets', [
        'headers' => [
            'Authorization' => "Bearer {$accessToken}",
            'Content-Type'  => 'application/json',
        ],
        'json' => $datasetDefinition
     ]);

     if ($response->getStatusCode() === 201) {
         return json_decode($response->getBody(), true); // returns dataset id, name, etc.
     }

     throw new \Exception("Failed to create Power BI dataset: " . $response->getBody());
    }

    public function getTemplates($token)
    {
        $client = new Client();
         
        $projectId = 'b.bfd3a80e-6576-4433-b8fa-758a8812b697';
        try {
            $response = $client->get("https://developer.api.autodesk.com/construction/forms/v1/projects/$projectId/form-templates", [
                'headers' => [
                    'Authorization' => "Bearer {$token}", // Add Base64 encoded credentials
                ], 
            ]);
            $response_data = json_decode($response->getBody(), true);

         
            $dataArrys = collect($response_data['data']);

            $resultData = $dataArrys->map(function ($dataArr){
                 return [
                     'id' => $dataArr['id'],
                    'updatedAt' => $dataArr['updatedAt'],
                 ];
            })->toArray();

            $cacheFile = base_path('public/template_form.json');
            $previousSnapshot = [];
            $decoded = null;

            if (file_exists($cacheFile)) {
                $json = file_get_contents($cacheFile);
                $decoded = json_decode($json, true);
            }
           if (is_array($decoded)) {
            $previousSnapshot = $decoded;
           } else {
            Log::warning('template_form.json contains invalid or null JSON. Resetting snapshot.');
           }

            $hasChanged = count($resultData) !== count($previousSnapshot)
            || array_diff_assoc(array_column($resultData, 'updatedAt', 'id'), array_column($previousSnapshot, 'updatedAt', 'id'));

            Log::info($hasChanged);

            if ($hasChanged) {
             // Push only if there are changes
            //  $response = $this->pushFormToPowerBI($response_data['data']);
             Log::info('has change');

             // Update the cache
             file_put_contents($cacheFile, json_encode($resultData));
            }else{
                // foreach ($response_data['data'] as $form) {
                //     $saveResult = $this->saveTemplates([
                //         'id' => $form['id'],    // ✅ extract values properly
                //         'name' => $form['name'],
                //         'projectId' => $form['projectId'],
                //     ]);
                //     if (!$saveResult['success']) {
                //         Log::warning($saveResult['message']);
                //     } else {
                //         Log::info($saveResult['message']);
                //     }
                // }
            }
            return ["success"=>true,"response"=>$response_data['data']];
        
        } catch (\Exception $e) {
            return ["success"=>false,"message"=>$e->getMessage()];
        }
    }

    public function saveTemplates(array $forms)
    {
        try{
       
         if (!Templates::where('template_id', $forms['id'])->exists()) {
            $newTemplate = new Templates();
            $newTemplate->template_id = $forms['id'];
            $newTemplate->name = $forms['name'];
            $newTemplate->project_id = $forms['projectId'];
            $newTemplate->save();
            return ['success' => true, 'message' => 'Saved: ' . $forms['id']];
         } else {
            return ['success' => false, 'message' => 'Duplicate skipped: ' . $forms['id']];
         }

        } catch (\Exception $e){
          Log::info('Error: ' . $e->getMessage());
          return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }


   public function saveForms(array $forms)
   {
    try {

        // Step 1: Find template and get its custom table name
        $template = Templates::where('template_id', $forms['template_id'])->first();

        if (!$template || empty($template->name)) {
            return ['success' => false, 'message' => 'Template not found or missing table name for template_id: ' . $forms['template_id']];
        }

        $tableName = 'template_' . Str::slug($template->name, '_');


        // Step 2: Check if table exists
        if (!Schema::hasTable($tableName)) {
            return ['success' => false, 'message' => 'Table does not exist: ' . $tableName];
        }

        // Step 3: Skip if duplicate
        $exists = DB::table($tableName)->where('id', $forms['id'])->exists();

        if ($exists) {
            return ['success' => false, 'message' => 'Duplicate skipped: ' . $forms['id']];
        }

        // Step 4: Insert into the dynamic table
        DB::table($tableName)->insert([
            'id' => $forms['id'],
            'template_id' => $forms['template_id'],
            'data' => json_encode($forms['pdfValues']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['success' => true, 'message' => 'Saved to ' . $tableName . ': ' . $forms['id']];
        
    } catch (\Exception $e) {
        Log::error('Error saving form: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
   }
    
}

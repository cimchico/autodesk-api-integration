<?php

namespace App\Jobs;

use App\Models\PoolingStatus;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\Templates;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

class PollAccessApiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        //
        $poolingStatus = $this->getPollingStatus();

        if ($poolingStatus->is_polling) {
            $response = $this->retrieveAccessToken();

            Log::info("Polled Access Token: " . json_encode($response));

            // Re-dispatch job to run again after 5 seconds
            self::dispatch()->delay(now()->addSeconds(15));
        } else {
            Log::info("Stopped polling: is_polling is false.");
        }
        Log::info(json_encode($poolingStatus));
    }

    private function getPollingStatus()
    {
        $poolingStatus = PoolingStatus::latest()->first();
        return (object)['is_polling' => $poolingStatus->is_polling];
    }


    private function retrieveAccessToken()
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
  
         Log::info('current time' . $currentTime);

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
                $templates = $this->getTemplates($tokens['access_token']);

                $callback['accessToken'] = $accessToken;
                // $callback['templates'] = $templates;
                $callback['forms'] = $forms;

            } catch (\GuzzleHttp\Exception\RequestException $e) {
                $callback['message'] = $e->getMessage();
            }
        }else {
            $callback['success'] = true;
            $callback['accessToken'] = $tokens['access_token'];

            $forms = $this->getForms($tokens['access_token']);
            $templates = $this->getTemplates($tokens['access_token']);

            $callback['forms'] = $forms;
            $callback['templates'] = $templates;
        }

       

        return $callback;
    }

    private function getTokensFromFile() 
    {
        
        $path = base_path('public\tokens.json');


        if (file_exists($path)) {
            return json_decode(file_get_contents($path), true);
        }
        
        return null;
    }

    private function saveTokens($access_token, $refresh_token, $expires_in)
    {
        $tokens = [
            'access_token' => $access_token,
            'refresh_token' => $refresh_token,
            'expires_in' => time() + $expires_in
        ];

        $path = base_path('public\tokens.json');
        file_put_contents($path, json_encode($tokens));
    }

    private function getForms($token)
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
            }
           if (is_array($decoded)) {
            $previousSnapshot = $decoded;
           } else {
            Log::warning('form_snapshot.json contains invalid or null JSON. Resetting snapshot.');
           }

            $hasChanged = count($resultData) !== count($previousSnapshot)
            || array_diff_assoc(array_column($resultData, 'updatedAt', 'id'), array_column($previousSnapshot, 'updatedAt', 'id'));


            Log::info('has change' . $hasChanged);
            if ($hasChanged) {
            
              foreach ($response_data['data'] as $form) {
                    $saveResult = $this->saveForms([
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
              file_put_contents($cacheFile, json_encode($resultData));
            }
          
            return ["success"=>true,"response"=>$resultData];
        
        } catch (\Exception $e) {
            return ["success"=>false,"message"=>$e->getMessage()];
        }


    }

    private function getTemplates($token)
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
            foreach ($response_data['data'] as $form) {
                    $saveResult = $this->saveTemplates([
                        'id' => $form['id'],    // ✅ extract values properly
                        'name' => $form['name'],
                        'projectId' => $form['projectId'],
                    ]);
                    if (!$saveResult['success']) {
                        Log::warning($saveResult['message']);
                    } else {
                        Log::info($saveResult['message']);
                    }
                }
             // Update the cache
             file_put_contents($cacheFile, json_encode($resultData));
            }
          
            return ["success"=>true,"response"=>$resultData];
        
        } catch (\Exception $e) {
            return ["success"=>false,"message"=>$e->getMessage()];
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
    public function saveTemplates(array $forms)
    {
        try {
             $isNew = false;
             
             if (!Templates::where('template_id', $forms['id'])->exists()) {
                 $newTemplate = new Templates();
            $newTemplate->template_id = $forms['id'];
            $newTemplate->name = $forms['name'];
            $newTemplate->project_id = $forms['projectId'];
            $newTemplate->save();
            $isNew = true;
             }

        // ✅ Sanitize table name from template name
        $tableName = 'template_' . Str::slug($forms['name'], '_');

        // ✅ Always ensure the table exists
        if (!Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->char('id', 36)->primary();
                $table->char('template_id')->nullable();
                $table->text('data')->nullable();
                $table->timestamps();
            });
        }

        return [
            'success' => true,
            'message' => ($isNew ? 'Saved and table created: ' : 'Table ensured for existing: ') . $forms['id']
        ];

    } catch (\Exception $e) {
        Log::info('Error: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
    }
    
    public function saveForms(array $forms)
    {
        try {
            Log::info('from get form' . json_encode($forms));
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

        $now = now();

        // Step 3: Insert or update
        DB::table($tableName)->updateOrInsert(
            ['id' => $forms['id']], // Unique key to match (acts as WHERE clause)
            [
                'template_id' => $forms['template_id'],
                'data' => json_encode($forms['pdfValues']),
                'updated_at' => $now,
                'created_at' => DB::raw("COALESCE(created_at, '$now')") // preserve if exists
            ]
        );

        return ['success' => true, 'message' => 'Saved/Updated in ' . $tableName . ': ' . $forms['id']];

    } catch (\Exception $e) {
        Log::error('Error saving form: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
    }


}

<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PowerBiConnectionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
        return view('layouts/power-bi-dashboard');

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        
    }

    public function getAccessToken()
    {
        
    }

    public function getRefreshHistory()
    {
        $client = new Client();
        $clientId = '045bc069-d62a-4e2e-8bfd-c4de99b86aeb';
        $clientSecret = 'oW38Q~ws_GiNNrrnBx6xm~sBDyJjRxsbB0i7qaj2';
        $tenantId  = '0615ec66-11f8-4f9f-b5ce-c9e4e0d80c37';

        try{
            
            $response = $client->post("https://login.microsoftonline.com/$tenantId/oauth2/v2.0/token",[
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'scope' => 'https://analysis.windows.net/powerbi/api/.default',
                ]
            ]);
            
            $data = json_decode($response->getBody(), true);
            
            $refreshData = $this->refreshData($data['access_token']);
            
            return ['success' => true, 'message' => $refreshData,];

        } catch (RequestException $e) {
            Log::info('error on refresh'. $e->getMessage());

            return ['success' => true, 'message' => $e->getMessage()];
        }
    }

    public function refreshData($accessToken)
    {
        $client = new Client();
      
        try{
     
            $response = $client->post('https://api.powerbi.com/v1.0/myorg/datasets/5dd4d9ee-c316-4eee-8da0-a8e3526c7635/refreshes',[
                'headers' => [
                    'Authorization' => "Bearer $accessToken",
                    'Content-type' => 'application/json'
                ],
                
            ]);
          
            $response_data = json_decode($response->getBody(), true);

            return $response_data;

        }catch(RequestException $e){
            if ($e->hasResponse()) {
        
                return (string)$e->getMessage();
            }
            return $e->getMessage();
        }
    }

    public function redirectToMicrosoft()
    {
        $clientId = '045bc069-d62a-4e2e-8bfd-c4de99b86aeb';
        $redirectUri = urlencode(route('auth.microsoft.callback'));
        $scopes = urlencode('https://analysis.windows.net/powerbi/api/.default offline_access openid profile');
        $tenantId  = '0615ec66-11f8-4f9f-b5ce-c9e4e0d80c37';
        $url = "https://login.microsoftonline.com/$tenantId/oauth2/v2.0/authorize?" .
        "client_id={$clientId}&response_type=code&redirect_uri={$redirectUri}&response_mode=query&scope={$scopes}";
        
        return redirect($url);
    }

   public function handleMicrosoftCallback(Request $request)
   {
     $code = $request->query('code');
     $clientId = '045bc069-d62a-4e2e-8bfd-c4de99b86aeb';
     $clientSecret = 'oW38Q~ws_GiNNrrnBx6xm~sBDyJjRxsbB0i7qaj2';
     $tenantId  = '0615ec66-11f8-4f9f-b5ce-c9e4e0d80c37';
     $client = new Client();

     $response = $client->post("https://login.microsoftonline.com/common/oauth2/v2.0/token", [
        'form_params' => [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => route('auth.microsoft.callback'),
            'scope' => 'https://analysis.windows.net/powerbi/api/.default offline_access openid profile',
        ]
     ]);

     $token = json_decode($response->getBody(), true);

     // Add expiration timestamp
     $token['expires_at'] = now()->addSeconds($token['expires_in'])->toDateTimeString();
     $path = base_path('public\ms_token.json');
     // Save token to file
     file_put_contents($path, json_encode($token));


     // Store token or use immediately
     $accessToken = $token['access_token'];

     // Optionally: call refreshData() to trigger dataset refresh
     return $this->refreshData($accessToken);
   }

   public function getValidAccessToken()
    {
        $path = base_path('public\ms_token.json');
        $token = json_decode(file_get_contents($path), true);
   
        
        if (now()->gte($token['expires_at'])) {
            $newToken = $this->refreshAccessToken($token['refresh_token']);
            $newToken['expires_at'] = now()->addSeconds($newToken['expires_in'])->toDateTimeString();
            
            file_put_contents($path, json_encode($newToken));
            $token = $newToken;
        }

        $this->refreshData($token['access_token']);
        return $token['access_token'];
    }
    
    
    public function refreshAccessToken($refreshToken)
    {
        $clientId = '045bc069-d62a-4e2e-8bfd-c4de99b86aeb';
        $clientSecret = 'oW38Q~ws_GiNNrrnBx6xm~sBDyJjRxsbB0i7qaj2';
        $tenantId = '0615ec66-11f8-4f9f-b5ce-c9e4e0d80c37';
        
        $client = new \GuzzleHttp\Client();
        
        
        $response = $client->post("https://login.microsoftonline.com/$tenantId/oauth2/v2.0/token", [
            'form_params' => [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
            'scope' => 'https://analysis.windows.net/powerbi/api/.default offline_access openid profile',
            ]
        ]);

        return json_decode($response->getBody(), true);
    }


    
}

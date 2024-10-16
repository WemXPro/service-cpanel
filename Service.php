<?php

namespace App\Services\Cpanel;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Services\ServiceInterface;
use App\Models\Package;
use App\Models\Order;

class Service implements ServiceInterface
{
    /**
     * Unique key used to store settings 
     * for this service.
     * 
     * @return string
     */
    public static $key = 'cpanel'; 

    public function __construct(Order $order)
    {
        $this->order = $order;
    }
    
    /**
     * Returns the meta data about this Server/Service
     *
     * @return object
     */
    public static function metaData(): object
    {
        return (object)
        [
          'display_name' => 'CPanel',
          'author' => 'WemX',
          'version' => '1.0.0',
          'wemx_version' => ['dev', '>=1.8.0'],
        ];
    }

    /**
     * Define the default configuration values required to setup this service
     * i.e host, api key, or other values. Use Laravel validation rules for
     *
     * Laravel validation rules: https://laravel.com/docs/10.x/validation
     *
     * @return array
     */
    public static function setConfig(): array
    {
        return [
            [
                "col" => "col-12",
                "key" => "cpanel::hostname",
                "name" => "CPanel Hostname",
                "description" => "Enter your CPanel hostname",
                "type" => "url",
                "rules" => ['required', 'active_url'], // laravel validation rules
            ],
            [
                "key" => "cpanel::api_user",
                "name" => "CPanel API User",
                "description" => "Enter your CPanel API User",
                "type" => "text",
                "rules" => ['required'], // laravel validation rules
            ],
            [
                "key" => "encrypted::cpanel::api_token",
                "name" => "CPanel Api Token",
                "description" => "Enter your CPanel API Token",
                "type" => "password",
                "rules" => ['required'], // laravel validation rules
            ],
        ];
    }

    /**
     * Define the default package configuration values required when creatig
     * new packages. i.e maximum ram usage, allowed databases and backups etc.
     *
     * Laravel validation rules: https://laravel.com/docs/10.x/validation
     *
     * @return array
     */
    public static function setPackageConfig(Package $package): array
    {
        $packageList = Service::api('/listpkgs')['package'] ?? [];

        $packages = collect($packageList)->mapWithKeys(function($package) {
            return [$package['name'] => $package['name']];
        });

        return [
            [
                "col" => "col-12",
                "key" => "package",
                "name" => "CPanel Package",
                "description" => "Select the CPanel Package that belongs to this Package",
                "type" => "select",
                "options" => $packages,
                "rules" => ['required'], // laravel validation rules
            ],
        ];
    }

    /**
     * Define the checkout config that is required at checkout and is fillable by
     * the client. Its important to properly sanatize all inputted data with rules
     *
     * Laravel validation rules: https://laravel.com/docs/10.x/validation
     *
     * @return array
     */
    public static function setCheckoutConfig(Package $package): array
    {
        return [];
    }

    /**
     * Define buttons shown at order management page
     *
     * @return array
     */
    public static function setServiceButtons(Order $order): array
    {
        return [];    
    }

    public function testConnection()
    {
        // using api list all packages
        try {
            $response = Service::api('/listpkgs');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Successfully connected to CPanel');
    }

    /**
     * This function is responsible for creating an instance of the
     * service. This can be anything such as a server, vps or any other instance.
     * 
     * @return void
     */
    public function create(array $data = [])
    {
        $order = $this->order;
        $user = $order->user;
        $package = $order->package;

        $data = [
            'domain' => $order->domain ?? $user->username . '.com',
            'username' => substr($user->username, 0, 6) . rand(10, 999), // get first 6 chars and add random number
            'contactemail' => $user->email,
            'password' => Str::random(12),
            'plan' => $package->data('package', 'default'),
        ];

        // make api call to create cpanel account
        $response = Service::api('/createacct', $data);

        $order->createExternalUser([
            'external_id' => 1, // optional
            'username' => $data['username'],
            'password' => $data['password'],
            'data' => [], // Additional data about the user as an array (optional)
         ]);
    }

    public static function api($endpoint, $data = [], $method = 'GET')
    {
        $hostname = settings('cpanel::hostname') . '/json-api/';
        $apiUsername = settings('cpanel::api_user');
        $apiToken = settings('encrypted::cpanel::api_token');

        $response = Http::withHeaders([
            'Authorization' => "whm {$apiUsername}:{$apiToken}",
        ])->$method($hostname . $endpoint, $data);

        if($response->failed()) {
            if($response->status() == 404) {
                throw new \Exception("404 Not Found | Invalid API endpoint {$endpoint} on host {$hostname}");
            }

            if($response->status() == 403) {
                throw new \Exception("403 Forbidden | Invalid API Token or User. <br> <br> Ensure the API token is correct and not expired and the user has the required permissions");
            }

            dd($response, $response->json(), $response->status());
        }

        return $response;
    }

    /**
     * This function is responsible for upgrading or downgrading
     * an instance of this service. This method is optional
     * If your service doesn't support upgrading, remove this method.
     * 
     * Optional
     * @return void
    */
    public function upgrade(Package $oldPackage, Package $newPackage)
    {
        return [];
    }

    /**
     * This function is responsible for suspending an instance of the
     * service. This method is called when a order is expired or
     * suspended by an admin
     * 
     * @return void
    */
    public function suspend(array $data = [])
    {
        return [];
    }

    /**
     * This function is responsible for unsuspending an instance of the
     * service. This method is called when a order is activated or
     * unsuspended by an admin
     * 
     * @return void
    */
    public function unsuspend(array $data = [])
    {
        return [];
    }

    /**
     * This function is responsible for deleting an instance of the
     * service. This can be anything such as a server, vps or any other instance.
     * 
     * @return void
    */
    public function terminate(array $data = [])
    {
        return [];
    }

}

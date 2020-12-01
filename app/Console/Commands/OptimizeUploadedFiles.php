<?php

namespace App\Console\Commands;

use Throwable;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Intervention\Image\ImageManager;
use Illuminate\Support\Facades\Storage;

class OptimizeUploadedFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:image-processing';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Download user images, process and store.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->storeImages();
    }

    public function storeImages()
    {
        try {
            $response = $this->getUsersFromApi();

            $manager = new ImageManager(array('driver' => 'imagick'));

            foreach ($response->data as $user) {
                $name = substr($user->picture, strrpos($user->picture, '/') + 1);

                $image = $this->processImage($manager->make($user->picture));

                $path = "users/$name";

                Storage::put($path, $image->__toString());

                $user->picture = $path;

                $this->updateUserImage((array) $user);
            }
        } catch (Throwable $th) {
            throw $th;
        }
    }

    public function getUsersFromApi()
    {
        $client = new Client([
            'headers' => [
                'app-id' => config('services.dummyapi.appid'),
            ],
        ]);

        $url = config('services.dummyapi.url') . '/user?limit=10';

        $response = $client->request('GET', $url);

        return json_decode((string) $response->getBody());
    }

    public function processImage($image)
    {
        $image->widen(100)->line(10, 10, 195, 195, function ($draw) {
            $draw->color('#f00');
            $draw->width(5);
        })->encode('jpg');

        return $image;
    }

    public function updateUserImage(array $data)
    {
        unset($data['id']);

        return User::updateOrCreate(
            ['email' => $data['email']],
            $data
        );
    }
}

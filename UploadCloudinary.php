<?php

namespace App\Jobs;

use App\Models\Design;

use Illuminate\Bus\Queueable;
use JD\Cloudder\Facades\Cloudder;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;

class UploadCloudinary implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $img_name, $design;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Design $design, $img_name)
    {
        $this->img_name = $img_name;
        $this->design = $design;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // set the path to the image to upload to cloudinary
        $path = storage_path() . '/app/public/uploads/designs/original/' . $this->img_name;

        // as per course
        $disk = $this->design->disk;
        $filename = $this->design->image;
        $original_file = storage_path() . '/uploads/original/' . $filename;

        try {
            Image::make($original_file)
                ->resize(800, 600, function ($constraint) {
                    $constraint->aspectRatio();
                })
                ->save($large = storage_path('uploads/large/' . $filename));


            Image::make($original_file)
                ->resize(250, 200, function ($constraint) {
                    $constraint->aspectRatio();
                })
                ->save($thumbnail = storage_path('uploads/thumbnail/' . $filename));

            if (Storage::disk($disk)
                ->put('uploads/designs/original/' . $filename, fopen($original_file, 'r+'))
            ) {
                File::delete($original_file);
            }

            if (Storage::disk($disk)
                ->put('uploads/designs/large/' . $filename, fopen($large, 'r+'))
            ) {
                File::delete($large);
            }

            if (Storage::disk($disk)
                ->put('uploads/designs/thumbnail/' . $filename, fopen($thumbnail, 'r+'))
            ) {
                File::delete($thumbnail);
            }
            // upload the image to cloudinary
            // a specific folder can be specified
            Cloudder::upload($path, null, ['folder' => 'original']);

            // get the url of the image on cloudinary
            $url = Cloudder::getResult()['secure_url'];

            // update the table
            $this->design->update([
                'upload_successful' => true,
                'image_url' => $url
            ]);
        } catch (\Exception $e) {
            \Log::error($e->getMessage());
        }
    }
}

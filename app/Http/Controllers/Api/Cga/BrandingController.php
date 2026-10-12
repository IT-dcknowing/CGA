<?php

namespace App\Http\Controllers\Api\Cga;

use App\Enums\BrandingAsset;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cga\UploadBrandingRequest;
use App\Http\Resources\CgaProfileResource;
use App\Support\ActiveSpace;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Logo, signature et cachet du CGA. Ils sont stockés sur le disque privé : la signature
 * et le cachet ne doivent jamais être accessibles par une URL publique.
 */
class BrandingController extends Controller
{
    private const DISK = 'local';

    public function store(UploadBrandingRequest $request, ActiveSpace $activeSpace): CgaProfileResource
    {
        $cga = $activeSpace->cga();
        $replacedFiles = [];

        foreach (BrandingAsset::cases() as $asset) {
            $file = $request->file($asset->value);

            if ($file === null) {
                continue;
            }

            $replacedFiles[] = $cga->{$asset->column()};
            $cga->{$asset->column()} = $file->store("cgas/{$cga->id}/branding", self::DISK);
        }

        $cga->save();
        Storage::disk(self::DISK)->delete(array_filter($replacedFiles));

        return new CgaProfileResource($cga->load(['taxRegion', 'taxCenter']));
    }

    public function show(BrandingAsset $asset, ActiveSpace $activeSpace): StreamedResponse
    {
        $path = $activeSpace->cga()->{$asset->column()};

        abort_if($path === null || ! Storage::disk(self::DISK)->exists($path), 404);

        return Storage::disk(self::DISK)->response($path);
    }
}

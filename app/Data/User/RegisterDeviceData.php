<?php

declare(strict_types=1);

namespace App\Data\User;

use App\Enums\DevicePlatform;
use App\Http\Requests\Api\V1\User\RegisterDeviceRequest;

final readonly class RegisterDeviceData
{
    public function __construct(
        public string $token,
        public DevicePlatform $platform,
    ) {}

    public static function fromRequest(RegisterDeviceRequest $request): self
    {
        return new self(
            token: trim($request->string('token')->value()),
            platform: DevicePlatform::from($request->string('platform')->value()),
        );
    }
}

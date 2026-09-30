<?php

declare(strict_types=1);

namespace App\Data\Chat;

use App\Http\Requests\Api\V1\Chat\StoreChatAttachmentRequest;
use Illuminate\Http\UploadedFile;

final readonly class StoreChatAttachmentData
{
    /** @param list<int>|null $waveform */
    public function __construct(
        public UploadedFile $file,
        public int $duration = 0,
        public int $width = 0,
        public int $height = 0,
        public ?array $waveform = null,
    ) {}

    public static function fromRequest(StoreChatAttachmentRequest $request): self
    {
        /** @var UploadedFile $file */
        $file = $request->file('file');

        return new self(
            file: $file,
            duration: $request->integer('duration'),
            width: $request->integer('width'),
            height: $request->integer('height'),
            waveform: $request->has('waveform')
                ? array_values(array_map('intval', (array) $request->input('waveform')))
                : null,
        );
    }
}

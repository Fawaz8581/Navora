<?php

namespace App\Services;

use getID3;

class VideoDurationInspector
{
    public function duration(string $path): ?float
    {
        $metadata = (new getID3)->analyze($path);
        $duration = $metadata['playtime_seconds'] ?? null;

        if (! is_array($metadata['video'] ?? null) || ! is_numeric($duration)) {
            return null;
        }

        return (float) $duration;
    }
}

<?php

namespace App\Services;

use App\Models\Document;

class DocumentCompliance
{
    /**
     * Required document types that are missing or expired on the given date.
     * An empty array means the vehicle/driver is compliant.
     *
     * @param  'vehicle'|'driver'  $docType
     */
    public function missing(string $docType, int $ownerId, string $date): array
    {
        $required = Document::REQUIRED[$docType];

        $valid = Document::query()
            ->where('doc_type', $docType)
            ->where('owner_id', $ownerId)
            ->whereIn('type', $required)
            ->whereDate('expires_at', '>=', $date)
            ->pluck('type')
            ->unique()
            ->all();

        return array_values(array_diff($required, $valid));
    }
}
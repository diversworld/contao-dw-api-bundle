<?php

declare(strict_types=1);

namespace Diversworld\ContaoDwApiBundle\Controller\Api;

use Symfony\Component\HttpFoundation\Request;

trait ApiControllerTrait
{
    /**
     * Decode JSON request bodies only when they contain an object/array payload.
     * This keeps controller actions from treating scalar JSON or malformed input
     * as valid API data.
     */
    private function decodeJsonPayload(Request $request): ?array
    {
        try {
            $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($payload) ? $payload : null;
    }

    /**
     * Contao models return database rows as strings. Normalizing timestamps here
     * keeps the API response format stable for the iOS app.
     */
    private function normalizeTimestampFields(array $row, array $fields): array
    {
        foreach ($fields as $field) {
            if (isset($row[$field]) && '' !== $row[$field]) {
                $row[$field] = (int)$row[$field];
            }
        }

        return $row;
    }

    private function normalizeBooleanFields(array $row, array $fields): array
    {
        foreach ($fields as $field) {
            if (isset($row[$field])) {
                $row[$field] = (bool)$row[$field];
            }
        }

        return $row;
    }
}

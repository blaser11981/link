<?php
declare(strict_types=1);

namespace VariuxLink\Services;

class NotionService
{
    private string $token;

    public function __construct()
    {
        $this->token = NOTION_TOKEN;
        if (empty($this->token)) {
            throw new \RuntimeException('NOTION_TOKEN not set in .env');
        }
    }

    public function queryDatabase(string $dbId, array $filter = []): array
    {
        $url = "https://api.notion.com/v1/databases/{$dbId}/query";
        $payload = ['page_size' => 100];
        if (!empty($filter)) $payload['filter'] = $filter;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->token,
                'Notion-Version: 2022-06-28',
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
        ]);

        $response = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($code !== 200) {
            throw new \RuntimeException("Notion query failed ({$code}): " . ($error ?: $response));
        }

        $data = json_decode($response, true);
        return $data['results'] ?? [];
    }

    public function extractValue(array $property): mixed
    {
        $type = $property['type'] ?? 'unknown';
        return match ($type) {
            'title'         => $property['title'][0]['plain_text'] ?? '',
            'rich_text'     => $property['rich_text'][0]['plain_text'] ?? '',
            'status'        => $property['status']['name'] ?? null,
            'people'        => implode(', ', array_map(fn($p) => $p['name'] ?? '', $property['people'] ?? [])),
            'url'           => $property['url'] ?? null,
            'number'        => $property['number'] ?? null,
            'relation'      => array_column($property['relation'] ?? [], 'id'),
            'multi_select'  => array_column($property['multi_select'] ?? [], 'name'),
            'select'        => $property['select']['name'] ?? null,
            'date'          => $property['date']['start'] ?? null, // todo: handle end & time
            'checkbox'      => $property['checkbox'] ?? false,
            'files'         => array_column($property['files'] ?? [], 'url'),
            'created_time'  => $property['created_time'] ?? null,
            'last_edited_time' => $property['last_edited_time'] ?? null,
            default         => null,
        };
    }
}
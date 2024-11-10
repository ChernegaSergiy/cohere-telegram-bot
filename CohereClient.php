<?php

declare(strict_types=1);

class CohereClient
{
    private string $api_key;

    private string $api_url = 'https://api.cohere.ai/v2/';

    /**
     * CohereClient constructor.
     *
     * @param  string  $api_key  The API key for accessing the Cohere API.
     */
    public function __construct(string $api_key)
    {
        $this->api_key = $api_key;
    }

    /**
     * Sends a chat request to the Cohere API.
     *
     * @param  array  $messages  The messages to send, each with a 'role' and 'content'.
     * @param  string  $model  The model to use for the request (default is 'command').
     * @param  int  $max_tokens  The maximum number of tokens to generate (default is 512).
     * @return array The response from the Cohere API.
     *
     * @throws Exception If no valid messages are found or if the API request fails.
     */
    public function chat(array $messages, string $model = 'command', int $max_tokens = 512) : array
    {
        $formattedMessages = [];

        foreach ($messages as $message) {
            if (! isset($message['content']) || '' === trim($message['content'])) {
                continue;
            }

            $formattedMessages[] = [
                'role' => $this->mapRole($message['role']),
                'content' => $message['content'],
            ];
        }

        if (empty($formattedMessages)) {
            throw new Exception('No valid message found for the request');
        }

        $endpoint = 'chat';
        $data = [
            'model' => $model,
            'messages' => $formattedMessages,
            'max_tokens' => $max_tokens,
            'temperature' => 0.7,
        ];

        return $this->makeRequest($endpoint, $data);
    }

    /**
     * Maps the role to the format expected by the Cohere API.
     *
     * @param  string  $role  The role to map (user, chatbot, system, tool).
     * @return string The mapped role.
     */
    private function mapRole(string $role) : string
    {
        $roleMap = [
            'user' => 'user',
            'chatbot' => 'assistant',
            'system' => 'system',
            'tool' => 'tool',
        ];

        return $roleMap[$role] ?? 'user';
    }

    /**
     * Makes a request to the Cohere API.
     *
     * @param  string  $endpoint  The API endpoint to call.
     * @param  array  $data  The data to send in the request.
     * @return array The response data from the API.
     *
     * @throws Exception If there is an error with the request.
     */
    private function makeRequest(string $endpoint, array $data) : array
    {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->api_url . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->api_key,
                'Accept: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode($data),
        ]);

        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            throw new Exception('Error making request to Cohere API: ' . $err);
        }

        $responseData = json_decode($response, true);

        file_put_contents('cohere_debug.log',
            date('Y-m-d H:i:s') . ' Request: ' . print_r($data, true) .
            "\nResponse: " . print_r($responseData, true) .
            "\nHTTP Code: " . $httpCode . "\n\n",
            FILE_APPEND
        );

        if (200 !== $httpCode) {
            throw new Exception('Cohere API error: ' .
                ($responseData['message'] ?? 'Unknown error') .
                ' (HTTP code: ' . $httpCode . ')'
            );
        }

        return $responseData;
    }
}

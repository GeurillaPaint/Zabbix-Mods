<?php
declare(strict_types=1);

namespace Modules\NetworkMap\Lib;

trait ActionHelperTrait {
    private function currentUserId(): string {
        return isset(\CWebUser::$data['userid']) ? (string) \CWebUser::$data['userid'] : '0';
    }

    private function respondJson(array $payload): void {
        $json = json_encode(
            $payload,
            JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        if ($json === false) {
            $json = '{"error":{"code":500,"message":"Failed to encode JSON response."}}';
        }

        $this->setResponse(new \CControllerResponseData([
            'main_block' => $json
        ]));
    }

    private function respondJsonError(string $message, int $code = 500, array $extra = []): void {
        $payload = array_merge([
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ], $extra);

        $this->respondJson($payload);
    }
}

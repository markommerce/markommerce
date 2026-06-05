<?php

declare(strict_types=1);

namespace Markommerce\Criteria\Position;

use Markommerce\Criteria\Exceptions\InvalidPositionTokenException;

class PositionCodec
{
    private const int FORMAT_VERSION = 1;

    /**
     * @throws InvalidPositionTokenException
     */
    public function encode(OffsetPosition|KeysetPosition $position): string
    {
        $payload = match (true) {
            $position instanceof OffsetPosition => [
                'v' => self::FORMAT_VERSION,
                't' => 'offset',
                'page' => $position->page,
            ],
            $position instanceof KeysetPosition => [
                'v' => self::FORMAT_VERSION,
                't' => 'keyset',
                'anchor' => $position->anchor,
                'id' => $position->id,
            ],
        };

        $json = json_encode($payload);

        if ($json === false) {
            throw InvalidPositionTokenException::malformed('payload could not be JSON-encoded');
        }

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /**
     * @throws InvalidPositionTokenException
     */
    public function decode(string $token): OffsetPosition|KeysetPosition
    {
        $padded = str_pad(
            strtr($token, '-_', '+/'),
            strlen($token) + (4 - strlen($token) % 4) % 4,
            '=',
        );

        $json = base64_decode($padded, strict: true);

        if ($json === false) {
            throw InvalidPositionTokenException::malformed('not valid base64url');
        }

        $data = json_decode($json, associative: true);

        if (!is_array($data)) {
            throw InvalidPositionTokenException::malformed('not valid JSON');
        }

        if (!isset($data['v']) || !is_int($data['v'])) {
            throw InvalidPositionTokenException::malformed('missing or non-integer version field');
        }

        if ($data['v'] !== self::FORMAT_VERSION) {
            throw InvalidPositionTokenException::unsupportedVersion($data['v']);
        }

        if (!isset($data['t']) || !is_string($data['t'])) {
            throw InvalidPositionTokenException::malformed('missing or non-string type field');
        }

        return match ($data['t']) {
            'offset' => $this->decodeOffset($data),
            'keyset' => $this->decodeKeyset($data),
            default => throw InvalidPositionTokenException::malformed("unknown type '{$data['t']}'"),
        };
    }

    /**
     * @param array<string, mixed> $data
     * @throws InvalidPositionTokenException
     */
    private function decodeOffset(array $data): OffsetPosition
    {
        if (!isset($data['page']) || !is_int($data['page'])) {
            throw InvalidPositionTokenException::malformed('offset token missing integer page field');
        }

        return new OffsetPosition(page: $data['page']);
    }

    /**
     * @param array<string, mixed> $data
     * @throws InvalidPositionTokenException
     */
    private function decodeKeyset(array $data): KeysetPosition
    {
        if (!isset($data['anchor']) || !is_array($data['anchor'])) {
            throw InvalidPositionTokenException::malformed('keyset token missing anchor array');
        }

        if (!isset($data['id']) || !is_int($data['id'])) {
            throw InvalidPositionTokenException::malformed('keyset token missing integer id field');
        }

        /** @var array<string, scalar> $anchor */
        $anchor = $data['anchor'];

        return new KeysetPosition(anchor: $anchor, id: $data['id']);
    }
}

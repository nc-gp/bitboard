<?php

namespace Api\POST;

use Api\Classes\ApiResponse;
use Api\Classes\ApiCodes;

use App\Classes\Database;

class Prefixes
{
    private int $code = ApiCodes::OK;
    private string $message = 'Prefix updated!';
    private array $errors = [];

    public function __construct(Database $db, array $data)
    {
        $type = $this->ReadAction($data);
        if($type === null)
            return;

        $prefixId = $this->ReadPrefixId($data);
        if($prefixId === null)
            return;

        $prefix = $db->Query('SELECT id, prefix_name, prefix_class FROM bit_prefixes WHERE id = ?', $prefixId)->FetchArray();

        if(empty($prefix))
        {
            $this->code = ApiCodes::NOT_FOUND;
            $this->message = 'Prefix not found.';
            return;
        }

        // The first two prefixes are built in and must not be changed.
        if((int) $prefix['id'] <= 2)
        {
            $this->code = ApiCodes::FORBIDDEN;
            $this->message = 'Built-in prefixes cannot be changed.';
            return;
        }

        if($type === 'delete')
        {
            $this->Delete($db, $prefix);
            return;
        }

        $this->Edit($db, $prefix, $data);
    }

    private function ReadAction(array $data): ?string
    {
        if(!array_key_exists('type', $data) || !is_string($data['type']))
        {
            $this->errors['type'] = 'Action must be a string.';
            $this->code = ApiCodes::BAD_REQUEST;
            $this->message = 'Invalid prefix data.';
            return null;
        }

        $type = strtolower(trim($data['type']));

        if(!in_array($type, array('edit', 'delete'), true))
        {
            $this->errors['type'] = 'Action must be "edit" or "delete".';
            $this->code = ApiCodes::BAD_REQUEST;
            $this->message = 'Invalid prefix data.';
            return null;
        }

        return $type;
    }

    private function ReadPrefixId(array $data): ?int
    {
        $prefixId = $data['prefixid'] ?? null;

        if(is_string($prefixId) && ctype_digit($prefixId))
            $prefixId = (int) $prefixId;

        if(!is_int($prefixId) || $prefixId < 1)
        {
            $this->errors['prefixid'] = 'Prefix ID must be a positive integer.';
            $this->code = ApiCodes::BAD_REQUEST;
            $this->message = 'Invalid prefix data.';
            return null;
        }

        return $prefixId;
    }

    private function Edit(Database $db, array $prefix, array $data): void
    {
        $prefixName = $this->ReadString($data, 'prefixname', 'Prefix name');
        $prefixClass = $this->ReadString($data, 'prefixclass', 'Prefix class');

        if($prefixClass !== null && !preg_match('/^[A-Za-z0-9_-]+$/', $prefixClass))
        {
            $this->errors['prefixclass'] = 'Prefix class may only contain letters, numbers, hyphens, and underscores.';
            $prefixClass = null;
        }

        if(!empty($this->errors))
        {
            $this->code = ApiCodes::BAD_REQUEST;
            $this->message = 'Invalid prefix data.';
            return;
        }

        $db->Query(
            'UPDATE bit_prefixes SET prefix_name = ?, prefix_class = ? WHERE id = ? AND id > 2',
            $prefixName,
            $prefixClass,
            (int) $prefix['id']
        );

        $this->message = sprintf('Prefix "%s" updated!', $prefixName);
    }

    private function Delete(Database $db, array $prefix): void
    {
        $usage = $db->Query('SELECT COUNT(*) AS total FROM bit_threads WHERE prefix_id = ?', (int) $prefix['id'])->FetchArray();

        if((int) ($usage['total'] ?? 0) > 0)
        {
            $this->code = ApiCodes::CONFLICT;
            $this->message = 'This prefix is used by one or more threads and cannot be deleted.';
            return;
        }

        $db->Query('DELETE FROM bit_prefixes WHERE id = ? AND id > 2', (int) $prefix['id']);

        if($db->AffectedRows() < 1)
        {
            $this->code = ApiCodes::NOT_FOUND;
            $this->message = 'Prefix not found.';
            return;
        }

        $this->message = sprintf('Prefix "%s" deleted!', $prefix['prefix_name']);
    }

    private function ReadString(array $data, string $key, string $label): ?string
    {
        if(!array_key_exists($key, $data) || !is_string($data[$key]))
        {
            $this->errors[$key] = $label . ' must be a string.';
            return null;
        }

        $value = trim($data[$key]);

        if($value === '')
        {
            $this->errors[$key] = $label . ' cannot be empty.';
            return null;
        }

        if(mb_strlen($value) > 64)
        {
            $this->errors[$key] = $label . ' cannot be longer than 64 characters.';
            return null;
        }

        return $value;
    }

    public function Response(): void
    {
        $response = ['message' => $this->message];

        if(!empty($this->errors))
            $response['errors'] = $this->errors;

        ApiResponse::Generate($this->code, $response);
    }
}

?>

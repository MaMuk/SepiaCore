<?php

namespace SepiaCoreUtilities;

class ScriptSandbox
{
    public static function execute(array $data, array $eventScript): array
    {
        $mainFunctionBody = $eventScript['mainFunctionBody'] ?? '';
        $luaRecord = self::phpArrayToLuaTable($data);

        if (empty($mainFunctionBody)) {
            return $data;
        }
        $wrappedLuaCode = <<<LUA
local record = $luaRecord

-- Begin user code
$mainFunctionBody
-- End user code

return record
LUA;


        $lua = new \LuaSandbox();
        $lua->setMemoryLimit(50 * 1024 * 1024);
        $lua->setCPULimit(10);
        try {
            $result = $lua->loadString($wrappedLuaCode)->call();
            return $result[0]; // return modified record
        } catch (\LuaSandboxRuntimeError $e) {
            Log::logMessage("Lua error: " . $e->getMessage(), "error");
            return $data;
        }
    }



    public static function getScriptArray(string $event, string $className): ?array
    {
        $filePath = ROOT_DIR . "/Entities/{$className}/scripts/{$event}.php";

        if (!is_readable($filePath)) {
            return null;
        }

        // Isolate scope and safely include the file
        $result = (function() use ($filePath) {
            $returned = include $filePath;
            return is_array($returned) ? $returned : null;
        })();

        return $result;
    }

    private static function phpArrayToLuaTable(array $arr): string {
        $items = [];

        foreach ($arr as $key => $value) {
            $luaKey = is_numeric($key) ? "[$key]" : "['$key']";

            if (is_string($value)) {
                $luaValue = "'" . addslashes($value) . "'";
            } elseif (is_bool($value)) {
                $luaValue = $value ? "true" : "false";
            } elseif (is_array($value)) {
                $luaValue = self::phpArrayToLuaTable($value); // recursion
            } else {
                $luaValue = $value;
            }

            $items[] = "$luaKey = $luaValue";
        }

        return "{ " . implode(", ", $items) . " }";
    }

}

<?php
namespace Owl\DataMapper\Type;

use \Owl\Service\DB\Expr;

class PgsqlArray extends \Owl\DataMapper\Type\Mixed {
    public function normalizeAttribute(array $attribute) {
        return array_merge([
            'strict' => true,
        ], $attribute);
    }

    public function normalize($value, array $attribute) {
        if ($value === null) {
            return array();
        }

        if (!is_array($value)) {
            throw new \UnexpectedValueException('Postgresql array must be of the type array');
        }

        return $value;
    }

    public function store($value, array $attribute) {
        if ($value === array()) {
            return null;
        }

        return self::encode($value);
    }

    public function restore($value, array $attribute) {
        if ($value === null) {
            return [];
        }

        return self::decode($value);
    }

    public function getDefaultValue(array $attribute) {
        return $attribute['default'] ?: array();
    }

    static public function encode(array $array) {
        if (!$array) {
            return NULL;
        }

        if (!is_array($array)) {
            return $array;
        }

        // 过滤掉会导致解析或保存失败的异常字符
        foreach ($array as $key => $val) {
            if ($val === NULL) {
                $val = 'NULL';
            } else {
                $val = rtrim($val, '\\');       // 以\结尾的字符串，在decode时会导致正则表达式无法解析

                $search = ['\\', "'", '"'];
                $replace = ['\\\\', "''", '\"'];
                $val = '"'.str_replace($search, $replace, $val).'"';
            }

            $array[$key] = $val;
        }

        return new Expr(sprintf("'{%s}'", implode(',', $array)));
    }

    static public function decode($pg_array) {
        if (!$pg_array) {
            return [];
        }

        $pg_array = trim($pg_array, '{}');

        // 如果没有包含"，直接简单的用,拆分
        if (strpos($pg_array, '"') === false) {
            $array = explode(',', $pg_array);
            foreach ($array as $key => $val) {
                if ($val === 'NULL') {
                    $array[$key] = NULL;
                }
            }

            return $array;
        }

        ////////////////////////////////////////////////////////////

        $array = [];

        // 每次循环解析出一个元素，每解析到一个元素，就在字符串内去掉这个元素
        // 字符串内的元素分两种情况，头尾有"或没有
        // 用"包含起来的元素里面会包含逃逸后的特殊字符串，需要用正则表达式来解析
        // 不用"包含的元素比较简单，直接找到最近的","来确定元素
        do {
            if (substr($pg_array, 0, 1) === '"') {
                if (!preg_match('/^"(.*)(?<!\\\)",?/U', $pg_array, $match)) {
                    break;
                }

                $array[] = $match[1];
                $pg_array = substr($pg_array, strlen($match[0])+1);
            } else {
                $pos = strpos($pg_array, ',');
                if ($pos === false) {
                    $val = $pg_array;
                    $pg_array = '';
                } else {
                    $val = substr($pg_array, 0, $pos);
                    $pg_array = substr($pg_array, $pos+1);
                }

                if ($val === 'NULL') {
                    $val = NULL;
                }

                $array[] = $val;
            }
        } while($pg_array);

        foreach ($array as $key => $val) {
            if ($val !== NULL) {
                $search = array('\"', '\\\\');
                $replace = array('"', '\\');
                $array[$key] = str_replace($search, $replace , $val);
            }
        }

        return $array;
    }
}

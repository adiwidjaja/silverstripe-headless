<?php

namespace ATW\Headless\View;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType;
use SilverStripe\ORM\SS_List;

/**
 */
class HeadlessViewer implements Flushable
{
    use Configurable;
    use Injectable;

    public function __construct()
    {
    }

    /**
     * Triggered early in the request when someone requests a flush.
     */
    public static function flush()
    {
    }

    public function process($item, $arguments = null, $inheritedScope = null)
    {
        $data = self::getHeadlessData($item, 3);
        $data["menu"] = $this->MenuData($item);

        return json_encode([
            "status" => 200,
            "data" => $data
        ]);
    }

    public function MenuData($item) {
        $menu = $item->Menu(1);
//        $menuData = self::castValue($menu, 2);
        $result = [];

        //Force section to be boolean
        foreach($menu as $item) {
            $itemData = self::getHeadlessData($item, 0);
            $itemData["section"] = isset($itemData["section"]) && $itemData["section"] == "1";

            if($itemData["section"]) {
                $childs = [];
                foreach($item->Children() as $child) {
                    $childItem = self::getHeadlessData($child, 0);
                    $childItem["section"] = isset($childItem["section"]) && $childItem["section"] == "1";
                    $childs[] = $childItem;
                }
                if($childs)
                    $itemData["children"] = $childs;
            }

            $result[] = $itemData;

        }
        return $result;
    }

    public static function castField(FieldType\DBField $dbfield)
    {
        switch (true) {
            case $dbfield instanceof FieldType\DBInt:
                return (int)$dbfield->RAW();
            case $dbfield instanceof FieldType\DBFloat:
                return (float)$dbfield->RAW();
            case $dbfield instanceof FieldType\DBBoolean:
                return (bool)$dbfield->RAW();
            case is_null($dbfield->RAW()):
                return null;
        }
        return $dbfield->RAW();
    }

    public static function castValue($value, $recurse=0) {
        if ($value instanceof FieldType\DBField) {
            return static::castField($value);
        } elseif ($value instanceof DataObject) {
            if($recurse)
                return self::getHeadlessData($value, $recurse); //Always recurse?
        } elseif ($value instanceof SS_List || is_array($value)) {
            if($recurse-1 <= 0)
                return null;
            $data = [];
            foreach($value as $val) {
                $data[] = self::castValue($val, $recurse-1);
            }
            return $data;
        } elseif (is_object($value)) {
            print_r($value);die();
        }else {
            // Hopefully string
            return Convert::raw2xml($value);
        }
    }

    public static function headlessFields($obj)
    {
        $rawFields = $obj->data()->config()->get("headless_fields");
        if(!$rawFields)
            $rawFields = [];

        $fields = [];

        //Always add ID and ClassName
        $fields['ID'] = 'id';
        $fields['ClassName'] = 'className';

        // Merge associative / numeric keys (from DataObject::summaryFields
        foreach ($rawFields as $key => $value) {
            if (is_int($key)) {
                $key = $value;
            }
            $fields[$key] = $value;
        }

        if ($obj->HeadlessFields) {
            $fields = array_merge($fields, $obj->HeadlessFields->toMap());
        }

        return $fields;
    }

    public static function getHeadlessData($obj, $recurse=0) {
        $fields = self::headlessFields($obj);

        $data = [];

        foreach($fields as $fieldName => $key) {
            if($fieldName == "Null") { //Dummy to remove fields
                if(isset($data[$fieldName]))
                    unset($data[$fieldName]);
                continue;
            }
            if (($obj->hasField($fieldName) && !is_object($obj->getField($fieldName)))
                || $obj->hasMethod("get{$fieldName}")
            ) {
                $value = $obj->obj($fieldName);
            } elseif ($obj->hasMethod('relField') && $obj->relField($fieldName)) {
                $value = $obj->relField($fieldName);
            } elseif ($obj->hasMethod($fieldName)) {
                $value = $obj->$fieldName();
            } else {
                $value = $obj->$fieldName;
            }

            $castedValue = self::castValue($value, $recurse);
            if($castedValue !== null)
                $data[$key] = $castedValue;

        }

        return $data;
    }

}

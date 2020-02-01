<?php

namespace ATW\Headless\Controllers;

use App\DataFormatter\JSONDataFormatter;
use SilverStripe\Core\Convert;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType;
use SilverStripe\ORM\SS_List;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\CMS\Controllers\ContentController;

class HeadlessController extends ContentController
{
    private $allowed_actions = [
        "index"
    ];

    public function __construct($dataRecord = null) {
        parent::__construct($dataRecord);
    }

    public function MenuData() {
        $menu = $this->Menu(1);
        $menuData = self::castValue($menu, 2);
        $result = [];

        //Force section to be boolean
        foreach($menuData as $item) {
            $item["section"] = isset($item["section"]) && $item["section"] == "1";
            $result[] = $item;
        }
        return $result;
    }

    public function index(HTTPRequest $request)
    {
        $this->getResponse()->addHeader('Content-Type', 'application/json');

        $data = self::getHeadlessData($this->data(), 3);
        $data["menu"] = $this->MenuData();

        return json_encode([
            "status" => 200,
            "data" => $data
        ]);
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
            if($recurse-1 == 0)
                return null;
            $data = [];
            foreach($value as $val) {
                $data[] = self::castValue($val, $recurse-1);
            }
            return $data;
        } else {
            return Convert::raw2xml($value);
        }
    }

    public static function headlessFields(DataObject $obj)
    {
        $rawFields = $obj->config()->get("headless_fields");
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


        return $fields;
    }

    public static function getHeadlessData(DataObject $obj, $recurse=0) {
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
            } elseif ($obj->hasMethod('relField')) {
                $value = $obj->relField($fieldName);
            } elseif ($obj->hasMethod($fieldName)) {
                $value = $obj->$fieldName();
            }

            $castedValue = self::castValue($value, $recurse);
            if($castedValue !== null)
                $data[$key] = $castedValue;

        }

        return $data;
    }

}

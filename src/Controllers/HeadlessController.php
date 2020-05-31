<?php

namespace ATW\Headless\Controllers;

use ATW\Headless\View\HeadlessViewer;
use SilverStripe\CMS\Controllers\ContentController;

class HeadlessController extends ContentController
{
    private $allowed_actions = [
        "index"
    ];

    public function __construct($dataRecord = null) {
        parent::__construct($dataRecord);
    }

    public function getViewer($action)
    {
        $this->getResponse()->addHeader('Content-Type', 'application/json');
        return HeadlessViewer::create();
    }

    public function customise($data)
    {
        $newKeys = array_keys($data);
        $data = parent::customise($data);
        $data->setField("HeadlessFields", array_combine($newKeys,$newKeys));

        return $data;
    }


}

<?php

declare(strict_types=1);

namespace console\controllers;

use Bitrix\Main\UI\Viewer\Renderer\Code;
use DateTime;
use yii\console\Controller;

class GoogleReviewController extends Controller
{
    private function handler()
    {
        static $ch;

        $api_key = "AIzaSyDQRwNO435IJoPxHffEb5345uGiv-i5Qiwvu8TEcs";

        /* To get placeId :
        https://maps.googleapis.com/maps/api/place/textsearch/json?key=yourKey&query=guru+technolabs
        placeId will be somewhere in output. :P
        */

        $placeid = "ChIJCYjVMr7l30IR_f5464rbfrmVquY";
        $parameters = "key=$api_key&placeid=$placeid";
        $url = "https://maps.googleapis.com/maps/api/place/details/json?$parameters";

        if ($ch === null) {
            $ch = \curl_init();

            \curl_setopt_array($ch, [
                CURLOPT_COOKIEJAR => APP_ROOT . '/common/runtime/reviews-cookie-request.jar',
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_FOLLOWLOCATION => 0,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.6) Gecko/20070725 Firefox/2.0.0.6',
                CURLOPT_URL => $url,
            ]);
        }

        return $ch;
    }

    public function request(): array
    {
        $ch = $this->handler();

        $json = \curl_exec($ch);
        curl_close($ch);

        return \json_decode($json, true);
    }

    public function actionParse()
    {
        $blockID = $blId;
        $sectionID = $secId;


        $request = $this->request();
        $reviews = ($request['result']['reviews']);

        foreach ($reviews as $review) {
            $element = new \CIBlockElement();
            $picture = null;
            $dt = new DateTime("@" . $review["time"]);
            if (!empty($review["profile_photo_url"])) {
                $picture = \CFile::MakeFileArray(\str_replace('{size}', 'islands-300', $review["profile_photo_url"]));

                \rename($picture['tmp_name'], $picture['tmp_name'] . '.jpg');

                $picture['name'] .= '.jpg';
                $picture['tmp_name'] .= '.jpg';
            }
            $time = $dt->format('d-m-Y');
            $code = $review["author_name"].' '.$time;
            $fields = [
                'ACTIVE_FROM' => \FormatDate('FULL', \strtotime($time ?? 'now')),
                'IBLOCK_ID' => $blockID,
                'IBLOCK_SECTION_ID' => $sectionID,
                'CODE' => $code,
                'NAME' => $review["author_name"],
                'PREVIEW_PICTURE' => $picture,
                'PREVIEW_TEXT' => $review["text"],
                'DETAIL_TEXT' => $review["text"],
            ];

            $reviewFields = \CIBlockElement::GetList([], ['IBLOCK_ID' => $blockID, 'CODE' => $code])->GetNext() ?: null;

            if ($reviewFields === null) {
                $elementID = $element->Add($fields);
            } else {
                $elementID = (int)$reviewFields['ID'];
                $element->Update($elementID, $fields);
            }

            \CIBlockElement::SetPropertyValuesEx($elementID, $blockID, [
                'TYPE' => $type,
                'RATING' => $review["rating"],
                'PROFILE_URL' => $review["author_url"],
            ]);

            if (!empty($picture)) {
                @\unlink($picture['tmp_name']);
            }
        }

        $tmpDir = APP_ROOT . '/public_html/upload/tmp';

        \passthru("find {$tmpDir} -type d -empty -delete 1>/dev/null 2>&1 &");
    }
}

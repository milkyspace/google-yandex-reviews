<?php

declare(strict_types=1);

namespace console\controllers;

use Bitrix\Main\UI\Viewer\Renderer\Code;
use yii\console\Controller;

class YandexReviewController extends Controller
{
    private function handler()
    {
        static $ch;

        if ($ch === null) {
            $ch = \curl_init();

            \curl_setopt_array($ch, [
                CURLOPT_COOKIEFILE     => APP_ROOT . '/common/runtime/reviews-cookie-request.jar',
                CURLOPT_COOKIEJAR      => APP_ROOT . '/common/runtime/reviews-cookie-request.jar',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT      => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.117 Safari/537.36',
                CURLOPT_REFERER        => 'https://yandex.ru/profile/2984645648052848?lr=213',
            ]);
        }

        return $ch;
    }

    public function request(string $url, array $query = []): array
    {
        $ch = $this->handler();

        \curl_setopt($ch, CURLOPT_URL, \rtrim($url . '?' . \http_build_query($query), '?&'));

        $json = \curl_exec($ch);

        return \json_decode($json, true);
    }

    public function actionParse()
    {
        $blockID   = $blId;
        $sectionID = $secId;
        $baseUrl   = 'https://yandex.ru/maps/api/business/fetchReviews';

        $csrfRequest = $this->request($baseUrl);
        $csrfToken   = $csrfRequest['csrfToken'];

        $page  = 1;
        $pages = 1;

        while ($page <= $pages) {
            $request = $this->request($baseUrl, [
                'ajax'       => 1,
                'businessId' => 29848052848,
                'csrfToken'  => $csrfToken,
                'page'       => $page,
                'pageSize'   => 10,
            ]);

            $pages   = $request['data']['params']['totalPages'];
            $reviews = $request['data']['reviews'];

            foreach ($reviews as $review) {
                $element = new \CIBlockElement();
                $picture = null;

                if (!empty($review['author']['avatarUrl'])) {
                    $picture = \CFile::MakeFileArray(\str_replace('{size}', 'islands-300', $review['author']['avatarUrl']));

                    \rename($picture['tmp_name'], $picture['tmp_name'] . '.jpg');

                    $picture['name']     .= '.jpg';
                    $picture['tmp_name'] .= '.jpg';
                }

                $code   = $review['reviewId'];
                $fields = [
                    'ACTIVE_FROM'       => \FormatDate('FULL', \strtotime($review['updatedTime'] ?? 'now')),
                    'IBLOCK_ID'         => $blockID,
                    'IBLOCK_SECTION_ID' => $sectionID,
                    'CODE'              => $code,
                    'NAME'              => $review['author']['name'],
                    'PREVIEW_PICTURE'   => $picture,
                    'PREVIEW_TEXT'      => $review['text'],
                ];

                $reviewFields = \CIBlockElement::GetList([], ['IBLOCK_ID' => $blockID, 'CODE' => $code])->GetNext() ? : null;

                if ($reviewFields === null) {
                    $elementID = $element->Add($fields);
                } else {
                    $elementID = (int) $reviewFields['ID'];
                    $element->Update($elementID, $fields);
                }

                \CIBlockElement::SetPropertyValuesEx($elementID, $blockID, [
                    'TYPE'             => $type,
                    'RATING'           => $review['rating'],
                    'PROFILE_URL'      => $review['author']['profileUrl'],
                    'PROFESSION_LEVEL' => $review['author']['professionLevel'],
                ]);

                if (!empty($picture)) {
                    @\unlink($picture['tmp_name']);
                }
            }

            ++$page;
        }

        $tmpDir = APP_ROOT . '/public_html/upload/tmp';

        \passthru("find {$tmpDir} -type d -empty -delete 1>/dev/null 2>&1 &");
    }
}

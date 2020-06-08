<?php
/**
 * Qcna
 * Omerlo integration for QCNA website.
 * php version 7.2.24
 *
 * @category Qcna_Omerlo_Integration
 * @package  Qcna
 * @author   Julien Lambert-Charette <julien.lambertcharette@gmail.com>
 * @license  ISC https://opensource.org/licenses/ISC
 * @link     https://qcna.qc.ca
 */
namespace modules\qcna;

use Craft;
use craft\events\ElementEvent;
use craft\services\Elements;
use craft\elements\Entry;
use linslin\yii2\curl;
use yii\base\Event;
use yii\base\Module;

class Qcna extends Module
{
    /**
     * Init
     *
     * @return void
     */
    public function init()
    {
        // Define a custom alias named after the namespace
        Craft::setAlias('@modules/qcna', $this->getBasePath());

        // Set the controllerNamespace
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'bar\\console\\controllers';
        } else {
            $this->controllerNamespace = 'bar\\controllers';
        }

        parent::init();

        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_SAVE_ELEMENT,
            function (ElementEvent $e) {
                // Exit function until module is completed
                //return;

                // @var Entry $entry
                $entry = $e->element;

                // Ignore if this isn't for an entry
                if (!$entry instanceof Entry) {
                    return;
                }

                // Ignore if this isn't from the news section
                if ($entry->section->handle !== 'news') {
                    return;
                }

                // Ignore events we don't care about
                if ($entry->propagating || $entry->resaving) {
                    return;
                }

                // Ignore if the entry isn't live or pending
                if (!in_array($entry->getStatus(), ['live', 'pending'])) {
                    return;
                }

                // Get access token
                $loginResponse = $this->login();
                if (!empty($loginResponse['access_token'])) {
                    $token = $loginResponse['access_token'];
                    // Push the article to Omerlo
                    $this->saveImage($entry, $token);
                    //$this->saveAuthor($entry, $token);
                    //$this->saveArticle($entry, $token);
                }
            }
        );

        Event::on(
            Elements::class,
            Elements::EVENT_AFTER_DELETE_ELEMENT,
            function (ElementEvent $e) {
                // Exit function until module is completed
                //return;

                $entry = $e->element;

                // Exit function if the event sender isn't an 'Entry'
                if (!$entry instanceof Entry) {
                    return;
                }

                // Exit function if the entry isn't from the 'News' section
                if ($entry->section->handle !== 'news') {
                    return;
                }

                // Get access token
                $loginResponse = $this->login();
                if (!empty($loginResponse['access_token'])) {
                    $token = $loginResponse['access_token'];
                    // Push the article to Omerlo
                    $this->deleteEntry($entry->id, $token);
                }
            }
        );
    }

    /**
     * Login to Omerlo
     *
     * @return array $response
     */
    public function login()
    {
        // Get the environment variables from phpdotenv
        $url = getenv('API_LOGIN_URL');
        $username = getenv('API_USERNAME');
        $password = getenv('API_PASSWORD');

        // Set login request params
        $params = json_encode(
            [
                'username' => $username,
                'password' => $password
            ]
        );

        // Set login headers
        $headers = [
            'Content-Type' => 'application/json'
        ];

        // Login cURL request
        $curl = new curl\Curl();
        $response = $curl->setRawPostData($params)
            ->setHeaders($headers)
            ->post($url);

        // Handle the cURL response
        switch ($curl->responseCode) {
        case 'timeout':
            Craft::$app->getSession()->setError('Omerlo Connection Timeout');
            break;
        case 404:
            Craft::$app->getSession()->setError('Omerlo 404');
            break;
        }

        return json_decode($response, true);
    }

    /**
     * Push the entry to Omerlo
     *
     * @param object $entry Entry
     * @param string $token API access token
     *
     * @return void
     */
    public function saveArticle($entry, $token)
    {
        $url = getenv('API_GRAPHQL_URL');
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json'
        ];

        // Save article
        $curl = new curl\Curl();
        $response = $curl->setPostParams(
            [
                "query" =>
                    "mutation {
                        saveArticle(
                            article: {
                                type: NEWS,
                                localizedTitles: [
                                    {
                                        locale: 'en',
                                        text: $entry->title
                                    }
                                ],
                                localizedLeads: [
                                    {
                                        locale: 'en',
                                        text: $entry->summary
                                    }
                                ],
                                authorIds: [
                                    $entry->author->email
                                ],
                                externalMappingList: [
                                    {
                                        externalMappingSystem: 'QCNA_WEBSITE',
                                        externalId: 'qcna_$entry->id',
                                    }
                                ],
                                canonicalUrlOverride: $entry->url,
                                completed: false
                            }
                        )
                        {
                            result {
                                id
                                hashedId
                                type
                                localizedTitles {
                                    locale
                                    text
                                }
                                localizedLeads {
                                    locale
                                    text
                                }
                                externalMappingList {
                                    externalId
                                }
                                canonicalUrlOverride
                                completed
                            }
                        }
                    }",
            ]
        )->setHeaders($headers)->post($url);
    }

    /**
     * Delete the entry from Omerlo
     *
     * @param integer $entryId Entry ID
     * @param string  $token   API access token
     *
     * @return void
     */
    public function deleteEntry($entryId, $token)
    {
        $url = getenv('API_GRAPHQL_URL');
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json'
        ];

        // TODO: Unpublish article on Omerlo and delete it
    }

    /**
     * Save a new author in omerlo
     *
     * @param string $token API access token
     *
     * @return void
     */
    public function saveAuthor($entry, $token)
    {
        $url = getenv('API_GRAPHQL_URL');
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json'
        ];

        // Save author
        $curl = new curl\Curl();
        $response = $curl->setPostParams(
            [
                "query" =>
                    "mutation {
                        saveAuthor(
                            author: {
                                name: $entry->author->fullname,
                                email: $entry->author->email,
                                externalMappingList: [
                                    {
                                        externalMappingSystem: 'QCNA_WEBSITE',
                                        externalId: $entry->author->email
                                    }
                                ]
                            }
                        )
                        {
                            result {
                                id
                                name
                                email
                                externalMappingList {
                                    externalId
                                }
                            }
                        }
                    }",
            ]
        )->setHeaders($headers)->post($url);
    }

    /**
     * Save a new image file in omerlo
     *
     * @param string $token API access token
     *
     * @return void
     */
    public function saveImage($entry, $token)
    {
        echo '<pre>';
        var_dump($entry->newsFeaturedImage->one()->filename);
        echo '</pre>';
        exit();

        $url = getenv('API_IMAGE_ENDPOINT');
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json'
        ];
        
        $image = $entry->newsFeaturedImage->one();

        $data = [
            'file' => $image->filename,
            'type' => 'CONTENT'
        ];

        // TODO: Save image in Omerlo and return its ID
        $curl = new curl\Curl();
        $response = $curl->setPostParams(
            [
                'body' => $data,

            ]
        )->setHeaders($headers)->post($url);

        return json_decode($response, true);
    }
}
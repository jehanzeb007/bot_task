<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use Symfony\Component\DomCrawler\Crawler;
use Google\Cloud\TextToSpeech\V1\TextToSpeechClient;
use Google\Cloud\Speech\V1\SpeechClient;
use Google\Cloud\Speech\V1\RecognitionConfig;
use Google\Cloud\Speech\V1\RecognitionAudio;

class RegisterBot extends Command
{
    protected $signature = 'bot:register';
    protected $description = 'Automate registration and email code extraction';

    public function __construct()
    {

        parent::__construct();
    }

    public function handle()
    {
        $cookieJar = new CookieJar();
        $client = new Client(['cookies' => $cookieJar]);

        // Step 1: Access the registration page
        $url = 'https://challenge.blackscale.media/register.php';
        try {
            $response = $client->get($url);
            $html = $response->getBody()->getContents();
            $crawler = new Crawler($html, $url);

            // Step 2: Extract the form and hidden fields
            $form = $crawler->filter('form')->form();
            $stoken = $crawler->filter('input[name="stoken"]')->attr('value');

            // Step 3: Fill the form fields, including the hidden stoken
            $formData = [
                'fullname' => 'Test User',
                'email' => 'testuser@example.com',
                'password' => 'password123',
                'stoken' => $stoken,
            ];
            $cookieJar = CookieJar::fromArray([
                'ctoken' => '6acef1a66c',
                'PHPSESSID' => 'b220176ae27ae66804b0f73d547a3a99'
            ], parse_url($url, PHP_URL_HOST));
            // Step 4: Submit the form using a POST request
            $actionUrl = $form->getUri(); // Get the form action URL
            print_r($formData);
            $response = $client->post($actionUrl, [
                'form_params' => $formData,
                'cookies' => $cookieJar, // Include the cookie jar for session management
            ]);

            $html = $response->getBody()->getContents();
            $crawler = new Crawler($html, $url);
            // Handle the response, e.g., check for success message
            if ($crawler->filter('.verification-box')->count()) {
                // Step 5: Extract email verification code
                $email_code = $this->getEmailContent();
                $this->postEmailCode($crawler, $client, $email_code);
                $this->info('Registration Email successful!');


                //Artisan::call('emails:read');
            } else {
                $this->error('Registration failed!');
            }
        } catch (RequestException $e) {
            $this->error('Request failed: ' . $e->getMessage());
        }
    }
    private function postEmailCode($crawler, $client, $email_code){

        try {
            $form = $crawler->filter('form')->form();
            $formData = [
                'code' => $email_code,
            ];
            $actionUrl = $form->getUri(); // Get the form action URL
            $response = $client->post($actionUrl, [
                'form_params' => $formData,
            ]);

            $html = $response->getBody()->getContents();
            $crawler = new Crawler($html);
            if ($crawler->filter('.g-recaptcha')->count()) {
                $this->solveRecaptcha($crawler);
                $this->info('Captcha done!');
            } else {
                $this->error('Registration failed!');
            }
        } catch (RequestException $e) {
            $this->error('Request failed: ' . $e->getMessage());
        }
    }
    private function solveRecaptcha($crawler)
    {
        // Find the audio captcha URL
        $audioUrl = $crawler->filter('.g-recaptcha audio-source')->attr('src');

        // Download the audio file
        $audioContent = file_get_contents($audioUrl);
        file_put_contents('captcha.mp3', $audioContent);

        // Convert audio file to text using Google Cloud Speech-to-Text
        $speechClient = new SpeechClient();
        $audio = (new RecognitionAudio())
            ->setContent(file_get_contents('captcha.mp3'));
        $config = (new RecognitionConfig())
            ->setEncoding(RecognitionConfig\AudioEncoding::MP3)
            ->setSampleRateHertz(16000)
            ->setLanguageCode('en-US');

        $response = $speechClient->recognize($config, $audio);

        foreach ($response->getResults() as $result) {
            return $result->getAlternatives()[0]->getTranscript();
        }

        $speechClient->close();

        return null;
    }

    private function getEmailContent()
    {
        // Implement the logic to fetch the email content
        // This could be through an IMAP/POP3 client or an email API
        //'Your verification code is: 352b20';
        return '123456';
    }

    private function extractVerificationCode($emailContent)
    {
        // Extract the verification code from the email content
        if (preg_match('/verification code is (\d+)/', $emailContent, $matches)) {
            return $matches[1];
        }
        return null;
    }
}

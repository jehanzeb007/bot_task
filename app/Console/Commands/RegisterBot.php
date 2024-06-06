<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use GuzzleHttp\Client;
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
        $client = new Client(['cookies' => true]);

        // Step 1: Access the registration page
        $response = $client->get('https://challenge.blackscale.media/register.php');
        $html = $response->getBody()->getContents();
        $crawler = new Crawler($html);

        // Step 2: Fill the form fields
        $form = $crawler->filter('form')->form();
        $formData = [
            'username' => 'testuser',
            'email' => 'testuser@example.com',
            'password' => 'password123',
            'confirm_password' => 'password123',
        ];

        // Step 3: Solve reCAPTCHA using TTS and STT
        $recaptchaSolution = $this->solveRecaptcha($crawler);

        if (!$recaptchaSolution) {
            $this->error('Failed to solve reCAPTCHA');
            return;
        }

        $formData['g-recaptcha-response'] = $recaptchaSolution;

        // Step 4: Submit the form
        $response = $client->submit($form, $formData);
        $html = $response->getBody()->getContents();
        $crawler = new Crawler($html);

        // Handle the response, e.g., check for success message
        if ($crawler->filter('.success-message')->count()) {
            $this->info('Registration successful!');

            // Step 5: Extract email verification code
            // Mock email reading process (you need a real email handler)
            $emailContent = $this->getEmailContent();
            $verificationCode = $this->extractVerificationCode($emailContent);

            $this->info('Verification code: ' . $verificationCode);
        } else {
            $this->error('Registration failed!');
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
        return 'Your verification code is 123456';
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

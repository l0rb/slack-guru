#!/usr/bin/php
<?php

   require('vendor/autoload.php');

   $guru = new Guru();

   class Guru {

      // these can be configured
      private $_api_token = '';
      private $_start_url = 'https://slack.com/api/rtm.start';
      private $_loop_sleep = 0.8; // how many seconds to sleep in the main loop
      private $_max_idle = 8; // if no event is received for this many seconds we send a ping
      private $_echo_log = false;
      private $_file_log = false;
      private $_trigger = ['technische analyse', 'guru'];

      // don't touch these 
      private $_generator = null;
      private $_ws = null;
      private $_users = array();
      private $_channels = array();
      private $_send_id = 0;

      private function _log($message)
      {
         $logline = date('H:i:s') . ' ' . $message . PHP_EOL;

         if($this->_echo_log)
            echo $logline;

         if($this->_file_log)
         {
            // todo: write logline to a file
         }
      }

      private function _get_ws_url()
      {
         $http = new \GuzzleHttp\Client();
         $r = $http->request('GET', $this->_start_url.'?token='.$this->_api_token);
         if($r->getStatusCode()!==200)
         {
            $this->_log('Did not get 200 status when asking for ws-url');
            return false;
         }
         $decoded = json_decode($r->getBody());
         if(!empty($decoded->url))
         {
            $this->_users = $decoded->users;
            foreach($decoded->channels as $channel)
            {
               $this->_channels[$channel->id] = $channel;
            }
            return $decoded->url;
         }
         else
         {
            $this->_log('Did not get a url when asking for ws-url');
            return false;
         }
      }

      private function _search_user($name)
      {
         foreach($this->_users as $user)
         {
            $look_in = [
               $user->name,
               // todo: why is this throwing errors??
               //$user->profile->first_name,
               //$user->profile->last_name
            ];
            foreach($look_in as $haystack)
            {
               if(strpos($haystack, $name)!==false)
                  return $user;
            }
         }
         return false;
      }

      private function _phrase()
      {
         return $this->_generator->generate();
      }

      private function _receive()
      {
         return $this->_ws->receive(true);
      }

      public function __construct()
      {
         // read config first
         $handle = fopen(__DIR__.'/conf.cfg', 'r');
         if ($handle) {
            while(($line = fgets($handle)) !== false) {
               if(strpos($line, '#')===0) continue; // ignore comments: lines starting with #
               $line = explode('|', $line, 2);
               if(count($line)!=2) continue;
               if($line[0]=='api-token') $this->_api_token = trim($line[1]);
               if($line[0]=='echo-log') $this->_echo_log = (bool)trim($line[1]);
               if($line[0]=='file-log') $this->_file_log = (bool)trim($line[1]);
            }
            fclose($handle);
         } else {
            $this->_log("Couldn't read config file.");
            return;
         }

         $this->_generator = new AnalysisGenerator();
         if(!($ws_url = $this->_get_ws_url())) return;
         $lorb = $this->_search_user('lorb');

         $this->_ws = new WebSocket\Client($ws_url);
         $this->_loop();
      }

      private function _ping()
      {
         $this->_log('ping');
         $message = [
            'type' => "ping"
         ];
         $this->_send($message);
      }

      private function _send($message)
      {
         $this->_send_id ++;
         $message['id'] = $this->_send_id;
         $this->_ws->send(json_encode($message));
      }

      private function _loop()
      {
         $start = microtime(true);
         $last_action = $start;
         $counter = 0;
         $time_limit = 0; // in seconds, for testing
         while(true)
         {
            $counter ++;
            if( $this->_do($counter) ) $last_action = microtime(true);
            $now = microtime(true);
            if(($now - $last_action) > $this->_max_idle) $this->_ping();
            if($time_limit>0 and ($now - $start) > $time_limit) break;
         }
      }

      // returns true if anything was received, false if not
      private function _do($counter)
      {
         usleep(1000000*$this->_loop_sleep);
         $event = json_decode($this->_receive());
         if(is_null($event)) return false;
         if(isset($event->type)) // error messages have no type
         {
            switch($event->type)
            {
            case 'message':
               if(strpos($event->channel, 'D')===0)
               {
                  $this->_handle_message($event);
               }
               else
               {
                  $this->_handle_message($event);
               }
               break;
            }
         }
         return true;
      }

      private function _handle_dm($event)
      {
         $message = [
            'type' => 'message',
            'channel' => $event->channel,
            'text' => $this->_phrase()
         ];
         $this->_send($message);
      }
      private function _handle_message($event)
      {
         if(isset($event->text) && $this->_trigger($event->text))
            $this->_handle_dm($event);
      }
      private function _trigger($text)
      {
         // prepare the text
         $text = iconv("utf-8","ascii//TRANSLIT", $text);  // replace umlauts and similar stuff with closest ascii match
         $text = preg_replace('/[^a-z ]+/', '', strtolower($text)); // throw out anything that is not a letter or a space

         // first do an exact search
         foreach($this->_trigger as $trigger)
            if(strpos($text,$trigger)!==false) return true;

         // getting fancy
         $words = explode(' ', $text);
         foreach($this->_trigger as $trigger)
         {
            $n = count(explode(' ', $trigger)); // $trigger is an n-graph, compare to each n-graph of the text
            foreach($words as $key => $word)
            {
               if(($key+$n)>count($words)) break; // not enough words left to do meaningful comparison
               for($i=1;$i<$n;$i++)
               {
                  $word .= ' '.$words[$key+$i];
               }
               $d = levenshtein($word, $trigger);
               if($d < ceil(strlen($trigger)/4)) return true; // allow higher distances if the string is longer
            }
         }
      }

   }

   class AnalysisGenerator {

      private $_noun = [
         'alligator technical indicator',
         'andrew\'s pitchfork',
         'aroon',
         'awesome oscillator',
         'bollinger band',
         'bear trap',
         'bull trap',
         'coppock-line',
         'steigendes dreieck',
         'fallendes dreieck',
         'symmetrisches dreieck',
         'elliotwellen',
         'fibonacci retracements',
         'fibonacci fan',
         'heikin ashi',
         'keltner channel',
         'head-and-shoulders formation',
         'MACD',
         'on balance volume',
         'renko chart',
         'swing index',
         'trend channel',
         'TRIX-indicator',
         'V-formation',
         'williams R'
      ];
      
      private $_movement_verb = [
         'steigen',
         'fallen',
         'sinken',
         'sich erholen',
         'einbrechen',
         'zurück pullen',
         'nach oben bouncen',
         'nach unten bouncen'
      ];

      private $_phrases = [
         'ganz klar %noun%. kurs wird %movement%',
         '%noun%! panik ist angebracht',
         '%noun% und zeitglich %noun% sind immer ein zeichen für %movement% des kurses',
         'sehe langfristig %noun% und kurzfristig %noun%. vorhersage: gemischt',
         'to the moon! %noun%, %noun% und %noun% zeigen es deutlich!',
         'bitcoin finally dead. %noun%, %noun% und %noun% zeigen es deutlich!',
         '%noun% in gigantischem ausmaß! kurs wird %movement%',
         'verlauf folgt %noun%. das kann langfristig nur durch %noun% erklärt werden',
         'der profi sieht hier %noun%, und handelt entsprechend',
         'oszillierende inverse-trend indikatoren in renko-charting channels in KLXN analyse liefern ein exaktes predictment aller pivot points on balance',
      ];
      
      public function generate()
      {
         return preg_replace_callback('/%[a-z]*%/',
            function($matches) {
               if($matches[0]=='%noun%') return r($this->_noun);
               if($matches[0]=='%movement%') return r($this->_movement_verb);
            },r($this->_phrases));
      }
   }

   function r($array) { return $array[array_rand($array)]; }


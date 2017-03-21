#!/usr/bin/php
<?php

   require('vendor/autoload.php');

   $guru = new Guru();

   class Guru {

      // todo: read this stuff from a config
      private $_api_token = '';
      private $_start_url = 'https://slack.com/api/rtm.start';

      private $_ws = null;
      private $_users = array();
      private $_channels = array();
      private $_send_id = 0;
      
      private $_generator = null;

      private function _log($message)
      {
         // todo: proper log
         echo date('H:i:s'), ' ', $message, PHP_EOL;
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
         return $this->_ws->receive();
      }

      public function __construct()
      {
         $this->_generator = new AnalysisGenerator();
         $ws_url = $this->_get_ws_url();
         $lorb = $this->_search_user('lorb');

         $this->_ws = new WebSocket\Client($ws_url);
         $this->_loop();
      }

      private function _loop()
      {
         $start = time();
         $counter = 0;
         $time_limit = 0; // in seconds, for testing
         while(true)
         {
            $counter ++;
            $this->_do($counter);
            if($time_limit>0 and (time() - $start) > $time_limit) break;
         }
      }

      private function _do($counter)
      {
         sleep(1);
         $event = json_decode($this->_receive());
         if(isset($event->type)) // error messages have no type
         {
            switch($event->type)
            {
               case 'message':
                  if(strpos($event->channel, 'D')===0)
                  {
                     $this->_handle_dm($event);
                  }
                  else
                  {
                     $this->_handle_message($event);
                  }
               break;
            }
         }
      }

      private function _handle_dm($event)
      {
         $this->_send_id ++;
         $message = [
            'id' => $this->_send_id,
            'type' => 'message',
            'channel' => $event->channel,
            'text' => $this->_phrase()
         ];
         $this->_ws->send(json_encode($message));
      }
      private function _handle_message($event)
      {
         if(isset($event->text) && strpos(strtolower($event->text),'technische analyse')!==false)
            $this->_handle_dm($event);
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


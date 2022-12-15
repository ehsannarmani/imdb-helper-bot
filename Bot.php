<?php

class Bot
{
    private $token;

    public $update;
    public $text;
    public $chat_id;
    public $first_name;
    public $last_name;
    public $full_name;
    public $username;
    public $message_id;
    public $callback_data;
    public $answer_id;
    public $database;
    public $step;
    public $user_link_markdown;
    public $user_link_html;
    public $user_copy_markdown;
    public $user_copy_html;
    public $is_send_contact;
    public $contact;
    public $is_message_forwarded;
    public $ReceivedPhoto;
    public $PhotoID;
    public $forward_from;
    public $forward_from_chat_id;


    public function __construct($token, $databaseConnection)
    {
        $this->token = $token;

        $this->update = json_decode(file_get_contents('php://input'));
        $message = $this->update->message;

        $this->text = $this->preventSqlInjection($message->text);

        $this->chat_id = $message->from->id;
        if ($this->chat_id == null || $this->chat_id == '') {
            $this->chat_id = $this->update->callback_query->from->id;
        }
        $chat = $message->chat;
        $this->first_name = $this->preventSqlInjection($chat->first_name == null ? "" : $chat->first_name);
        $this->last_name = $this->preventSqlInjection($chat->last_name == null ? "" : $chat->last_name);

        if (!$this->validateVariable($this->first_name)) {
            $this->first_name = $this->update->callback_query->from->first_name == null ? "" : $this->update->callback_query->from->first_name;
            $this->last_name = $this->update->callback_query->from->last_name == null ? "" : $this->update->callback_query->from->last_name;
        }

        $this->full_name = $this->first_name . " " . $this->last_name;
        $this->username = $message->chat->username;
        if (!$this->validateVariable($this->username)) {
            $this->username = $this->update->callback_query->from->username;
        }
        $this->message_id = $message->message_id;
        if (!$this->validateVariable($this->message_id)) {
            $this->message_id = $this->update->callback_query->message->message_id;
        }
        $this->callback_data = $this->update->callback_query->data;
        $this->answer_id = $this->update->callback_query->id;
        $this->database = $databaseConnection;
        mysqli_set_charset($this->database, 'utf8mb4');


        $getStep = mysqli_query($this->database, "SELECT * FROM `steps` WHERE `chat_id`=" . $this->chat_id);
        $this->step = mysqli_fetch_assoc($getStep)['step'];

//        $this->user_link_markdown = "[{$this->full_name}](tg://user?id={$this->chat_id})";
//        $this->user_link_html = "<a href='tg://user?id={$this->chat_id}'>{$this->full_name}</a>";

        $this->user_link_markdown = "[{$this->chat_id}](tg://user?id={$this->chat_id})";
        $this->user_link_html = "<a href='tg://user?id={$this->chat_id}'>{$this->chat_id}</a>";

        $this->user_copy_markdown = "`{$this->chat_id}`";
        $this->user_copy_html = "<code>{$this->chat_id}</code>";

        $this->is_message_forwarded = $this->validateVariable($message->forward_from)
            || $this->validateVariable($message->forward_date)
            || $this->validateVariable($message->forward_sender_name);

        $this->is_send_contact = $this->validateVariable($message->contact);
        $this->contact = $message->contact->phone_number;

        $this->ReceivedPhoto = $this->validateVariable($message->photo);
        $this->PhotoID = $message->photo[1]->file_id;
        if (!$this->validateVariable($this->PhotoID)) {
            $this->PhotoID = $message->photo[0]->file_id;
        }
        $this->forward_from = $message->forward_from;

        $this->forward_from_chat_id = $this->forward_from->id;

    }

    public function preventSqlInjection($value)
    {
        $value = str_replace("'", '', $value);
        $value = str_replace("", '', $value);
        $value = str_replace("\\", '', $value);
        $value = str_replace("&", '', $value);
        $value = str_replace("OR", '', $value);
        return str_replace("$", '', $value);
    }

    public function validateVariable($variable)
    {
        if ($variable == null || $variable == '') {
            return false;
        } else {
            return true;
        }
    }

    public function bot($method, $datas = [])
    {
        $url = "https://api.telegram.org/bot" . $this->token . "/" . $method;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $datas);
        $res = curl_exec($ch);
        if (curl_error($ch)) {

            var_dump(curl_error($ch));

        } else {
            return json_decode($res);
        }
    }

    public function forwardMessage($chat_id, $target, $msg_id)
    {
        return $this->bot('forwardMessage', [
            'chat_id' => $target,
            'from_chat_id' => $chat_id,
            'message_id' => $msg_id
        ]);
    }

    public function copyMessage($chat_id,$from_chat_id,$msg_id){
        $this->bot('copyMessage',[
            'chat_id'=>$chat_id,
            'from_chat_id'=>$from_chat_id,
            'message_id'=>$msg_id
        ]);
    }

    public function sendMessage($chat_id = null, $msg, $parse_mode = 'html', $key = null, $normal_key = false, $reply = true)
    {

        if ($chat_id){
            $reply = false;
        }
        if ($key) {

            if ($normal_key) {
                $keyboard = array(
                    'keyboard' => $key,
                    'resize_keyboard' => true,
                    'selective' => true,
                    'one_time_keyboard' => false
                );
                $keyboard = json_encode($keyboard);
            } else {
                $keyboard = json_encode([
                    'inline_keyboard' => $key
                ]);
            }

            if ($reply) {
                if ($this->callback_data) {
                    return $this->bot('sendMessage', [
                        'chat_id' => $chat_id == null ? $this->chat_id : $chat_id,
                        'text' => $msg, 'parse_mode' => $parse_mode,
                        'reply_markup' => $keyboard,'disable_web_page_preview'=>true
                    ]);
                } else {
                    return $this->bot('sendMessage', [
                        'chat_id' => $chat_id == null ? $this->chat_id : $chat_id,
                        'text' => $msg, 'parse_mode' => $parse_mode,
                        'reply_markup' => $keyboard, 'reply_to_message_id' => $this->message_id,'disable_web_page_preview'=>true
                    ]);
                }


            } else {
                return $this->bot('sendMessage', [
                    'chat_id' => $chat_id == null ? $this->chat_id : $chat_id,
                    'text' => $msg, 'parse_mode' => $parse_mode,
                    'reply_markup' => $keyboard,'disable_web_page_preview'=>true
                ]);
            }
        } else {
            if ($reply) {
                if ($this->callback_data) {
                    return $this->bot('sendMessage', [
                        'chat_id' => $chat_id == null ? $this->chat_id : $chat_id,
                        'text' => $msg, 'parse_mode' => $parse_mode,'disable_web_page_preview'=>true
                    ]);
                } else {
                    return $this->bot('sendMessage', [
                        'chat_id' => $chat_id == null ? $this->chat_id : $chat_id,
                        'text' => $msg, 'parse_mode' => $parse_mode, 'reply_to_message_id' => $this->message_id,'disable_web_page_preview'=>true
                    ]);
                }
            } else {
                return $this->bot('sendMessage', [
                    'chat_id' => $chat_id == null ? $this->chat_id : $chat_id,
                    'text' => $msg, 'parse_mode' => $parse_mode,'disable_web_page_preview'=>true
                ]);
            }
        }
    }

    public function sendPhoto($chat_id, $photo, $caption, $parse_mode = 'html')
    {
        if (!$chat_id) {
            $chat_id = $this->chat_id;
        }
        return $this->bot('sendPhoto', [
            'photo' => $photo,
            'caption' => $caption,
            'chat_id' => $chat_id,
            'parse_mode' => $parse_mode
        ]);
    }
    public function sendSticker($chat_id,$sticker,$key=null,$reply=false,$reply_to = null){
        if ($key){
            if ($reply){
                $this->bot('sendSticker',[
                    'chat_id'=>$chat_id,
                    'sticker'=>$sticker,
                    'reply_to_message_id'=>$reply_to,
                    'reply_markup'=>json_encode([
                        'inline_keyboard'=>$key
                    ])
                ]);
            }else{
                $this->bot('sendSticker',[
                    'chat_id'=>$chat_id,
                    'sticker'=>$sticker,
                    'reply_markup'=>json_encode([
                        'inline_keyboard'=>$key
                    ])
                ]);
            }
        }else{
            if ($reply){
                $this->bot('sendSticker',[
                    'chat_id'=>$chat_id,
                    'sticker'=>$sticker,
                    'reply_to_message_id'=>$reply_to,
                ]);
            }else{
                $this->bot('sendSticker',[
                    'chat_id'=>$chat_id,
                    'sticker'=>$sticker,
                ]);
            }
        }
    }

    public function deleteMessage($chat_id = null, $msgID = null)
    {
        $this->bot('deleteMessage', [
            'chat_id' => $chat_id == null ? $this->chat_id : $chat_id,
            'message_id' => $msgID == null ? $this->message_id : $msgID
        ]);
    }

    public function editMessage($chat_id = null, $msg, $parse_mode = 'html', $key = null, $normal_key = false, $msgID = null)
    {
        if ($key) {

            if ($normal_key) {
                $keyboard = array(
                    'keyboard' => $key,
                    'resize_keyboard' => true,
                    'selective' => true,
                    'one_time_keyboard' => false
                );
                $keyboard = json_encode($keyboard);
            } else {
                $keyboard = json_encode([
                    'inline_keyboard' => $key
                ]);
            }

            $this->bot('editMessageText', [
                'chat_id' => $chat_id == null ? $this->chat_id : $chat_id,
                'text' => $msg, 'parse_mode' => $parse_mode,
                'reply_markup' => $keyboard, 'message_id' => $msgID == null || $msgID == '' ? $msgID = $this->message_id : $msgID
            ]);
        } else {
            $this->bot('editMessageText', [
                'chat_id' => $chat_id == null ? $this->chat_id : $chat_id,
                'text' => $msg, 'parse_mode' => $parse_mode, 'message_id' => $msgID == null || $msgID == '' ? $msgID = $this->message_id : $msgID
            ]);
        }
    }

    public function answerQuery($alert, $text)
    {
        $this->bot('answerCallbackQuery', [
            'callback_query_id' => $this->answer_id,
            'text' => $text,
            'show_alert' => $alert
        ]);
    }

    public function changeStep($step)
    {
        $chat = $this->chat_id;
        $this->removeStep();
        mysqli_query($this->database, "INSERT INTO `steps` (`chat_id`,`step`) VALUES ('$chat','$step')");
    }

    public function saveData($key, $data)
    {
        $chat = $this->chat_id;
        mysqli_query($this->database, "INSERT INTO `saved_data` (`chat_id`, `key`, `data`) VALUES ('$chat','$key','$data')");
    }

    public function getData($key)
    {
        $chat = $this->chat_id;
        $data = mysqli_fetch_assoc(mysqli_query($this->database, "SELECT * FROM `saved_data` WHERE `chat_id`='$chat' AND `key`='$key'"));
        return $data['data'];
    }

    public function removeStep()
    {
        $chat = $this->chat_id;
        mysqli_query($this->database, "DELETE FROM `steps` WHERE `chat_id`='$chat'");
    }

    public function removeAllData()
    {
        $chat = $this->chat_id;
        mysqli_query($this->database, "DELETE FROM `steps` WHERE `chat_id`='$chat'");
        mysqli_query($this->database, "DELETE FROM `saved_data` WHERE `chat_id`='$chat'");
    }

    public function needle($target, $needle)
    {
        if (strpos($target, $needle) !== false) {
            return true;
        } else {
            return false;
        }
    }

    function getUser($chat_id = null)
    {
        if (!$chat_id) {
            $chat_id = $this->chat_id;
        }
        return mysqli_fetch_assoc(mysqli_query($this->database, "SELECT * FROM `users` WHERE `chat_id`='$chat_id'"));
    }

    function updateUser($chat_id, $field, $data)
    {
        if (!$chat_id) {
            $chat_id = $this->chat_id;
        }
        mysqli_query($this->database, "UPDATE `users` SET `{$field}`='$data' WHERE `chat_id`='$chat_id'");
    }

    function checkJoin($channel,$chat_id = null)
    {
        if (!$chat_id){
            $chat_id = $this->chat_id;
        }
        $request = $this->bot('getChatMember', [
            'chat_id' => "@$channel",
            'user_id' => $chat_id
        ]);
        return $request->result->status == 'creator' || $request->result->status == 'administrator' || $request->result->status == 'member';

    }

    public function checkJoinToChannels($enable, $channels, $sendMsg, $inviter = '')
    {


        if ($enable) {
            $count = 0;
            for ($i = 0; $i < sizeof($channels); $i++) {
                if ($this->checkJoin($channels[$i])) {
                    $count++;
                }
            }
            if ($count < sizeof($channels)) {


                if ($sendMsg) {
//                    $this->sendMessage(null, "برای حمایت از ما ، لطفا در تمامی کانال های ما عضو شوید 👇", 'html', [
//                        [
//                            ['text' => "کانال دوم", 'url' => "https://t.me/" . $channels[1]], ['text' => "کانال اول", 'url' => "https://t.me/" . $channels[0]],
//                        ], [
//                            ['text' => "کانال چهارم", 'url' => "https://t.me/" . $channels[3]], ['text' => "کانال سوم", 'url' => "https://t.me/" . $channels[2]],
//                        ], [
//                            ['text' => 'عضو شدم ✅', 'callback_data' => "imJoinedToRequireChannels$inviter"]
//                        ]
//                    ]);
                    $this->sendMessage(null, "برای حمایت از ما ، لطفا در کانال ما عضو شوید 👇", 'html', [
                        [
                            ['text' => "ورود به کانال 📢", 'url' => "https://t.me/" . $channels[0]],
                        ],[
                            ['text' => 'عضو شدم ✅', 'callback_data' => "imJoinedToRequireChannels$inviter"]
                        ]
                    ]);
                } else {
                    $this->answerQuery(false, 'در تمامی چنل ها عضو نشده اید ❌');
                    exit();
                }

                exit();
            }
        }
    }

    public function requestUserPhone($msg)
    {
        $this->bot('sendMessage', [
            'text' => $msg,
            'chat_id' => $this->chat_id,
            'reply_markup' => json_encode(['keyboard' => [
                [['text' => 'ارسال شماره تلفن', 'request_contact' => true]],], 'resize_keyboard' => true])
        ]);
    }

    public function AntiFake($enable, $text)
    {
        if ($enable) {
            $this->requestUserPhone("🔸 لطفا برای تایید هویت ، شماره تلفن خود را با دکمه زیر ارسال نمایید.");
            $this->changeStep('sendingPhone');
            if (!$text) {
                $text = $this->text;
            }
            $this->saveData('text', $text);
            exit();
        }
    }

    public function AuthenticationPhone()
    {
        if (!$this->is_message_forwarded) {
            if ($this->is_send_contact) {
                if ($this->update->message->contact->user_id == $this->chat_id) {
                    $contact = $this->contact;
                    $first_letter = substr($contact, 0, 1);
                    if ($first_letter != '+') {
                        $contact = '+' . $contact;
                    }
                    if (substr($contact, 0, 3) == '+98') {
                        $this->removeStep();
                        return true;
                    } else {
                        return false;
                    }
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    public function userRegistered()
    {
        $chat = $this->chat_id;
        return mysqli_num_rows(mysqli_query($this->database, "SELECT * FROM `users` WHERE `chat_id`='$chat'")) >= 1;
    }

    public function AuthNumberText()
    {
        if (!is_numeric($this->text)) {
            $this->sendMessage(null, 'مقدار وارد شده نامعتبر است ❌');
            exit();
        }
    }

    public function AuthTextNull()
    {
        if ($this->text == null || $this->text == '') {
            exit();
        }
    }

    public function BotAdminInChannel($channel)
    {
        $bot_chat_id = explode(':', $this->token)[0];
        return $this->bot('getChatMember', [
                'chat_id' => "@$channel",
                'user_id' => $bot_chat_id
            ])->result->status == 'administrator';
    }
    public function getMessageType()
    {
        $sticker = $this->update->message->sticker;
        $photo = $this->update->message->photo;
        $video = $this->update->message->video;
        $animation = $this->update->message->animation;
        $document = $this->update->message->document;
        $text = $this->update->message->text;
        $voice = $this->update->message->voice;

        if ($sticker != null) {
            return "sticker";
        } elseif ($photo != null) {
            return "photo";
        } elseif ($video != null) {
            return "video";
        } elseif ($animation != null) {
            return "animation";
        } elseif ($document != null) {
            return "document";
        } elseif ($text != null) {
            return "text";
        } elseif ($voice != null) {
            return "voice";
        } else {
            return null;
        }
    }



}

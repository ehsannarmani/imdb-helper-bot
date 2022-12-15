<?php

require_once "Bot.php";
require_once "shd.php";

$bot = new Bot("token",null);

$update = $bot->update;
file_put_contents("update.json",json_encode($update));
$chat_id = $update->message->chat->id;
$uploadChannelId = "-1001850327015";

if ($bot->callback_data){
    $chat_id = $update->callback_query->message->chat->id;

    $message_id = $update->callback_query->message->message_id;
    if ($bot->needle($bot->callback_data,"movie")){
        $bot->deleteMessage($chat_id,$message_id);
        $status = $bot->sendMessage($chat_id,"⚙️ Fetching data...");
        $imdbId = str_replace("movie","",$bot->callback_data);
        $details = getMovieDetails($imdbId);
        $title = $details['title'];
        $story = $details['story'];
        $rating = $details['rating'];
        $genres = join(" ",array_map(function ($item){
            return "#$item";
        },$details['generes']));

        $actors = join(" ",array_map(function ($item){
            return "<i><a href='https://google.com'>$item</a></i> / ";
        },$details['starActors']));
        $creators = join(" ",array_map(function ($item){
            return "<i><a href='https://google.com'>$item</a></i> / ";
        },$details['creators']));
        $bot->deleteMessage($chat_id,$status->result->message_id);
        $bot->bot('sendPhoto',[
            'chat_id'=>$chat_id,
            'photo'=>$details['poster'],
            'caption'=>"$genres\n\n📺 <strong>$title</strong>\n\n<strong>🖊 Story</strong>: $story\n\n<strong>⭐️ Rating: $rating/10</strong>\n<strong>Top Actors:</strong> $actors\n<strong>Creators: </strong>$creators",
            'parse_mode'=>'html',
            'reply_markup' => json_encode([
                'inline_keyboard' =>[
                    [
                        ['text'=>'Episodes','callback_data'=>"episodes$imdbId"]
                    ] ,[
                        ['text'=>'Trailer','callback_data'=>"trailer$imdbId"],['text'=>"Similar Movies",'callback_data'=>"similar$imdbId"]
                    ],[
                        ['text'=>'Photos','callback_data'=>"photos$imdbId"],['text'=>'Actors Photos','callback_data'=>"actorPhotos$imdbId"],
                    ],[
                        ['text'=>'See In IMDB','url'=>"https://www.imdb.com/title/$imdbId/?ref_=fn_al_tt_1"]
                    ]
                ]
            ])
        ]);
    }else if ($bot->needle($bot->callback_data,"trailer")){
        $imdbId = str_replace("trailer","",$bot->callback_data);
        $status= $bot->sendMessage($chat_id,"⚙️ Getting Trailer...");
        $details = getMovieDetails($imdbId);
        $bot->deleteMessage($chat_id,$status->result->message_id);
        $status= $bot->sendMessage($chat_id,"📥 Uploading Trailer...");
        $title = $details['title'];
        $poster = urlencode($details['poster']);
        $trailer = urlencode($details['trailer']);
        $uploadedMessageId = json_decode( file_get_contents("madeline.php/index.php?link=$trailer&poster=$poster"))->messageId;
        $bot->deleteMessage($chat_id,$status->result->message_id);
        $bot->bot('copyMessage',[
            'from_chat_id'=>$uploadChannelId,
            'chat_id'=>$chat_id,
            'message_id'=>$uploadedMessageId,
            'caption'=>"Trailer of <strong>$title</strong>",
            'parse_mode'=>'html'
        ]);

exit();
    }else if ($bot->needle($bot->callback_data,"similar")){
        $imdbId = str_replace("similar","",$bot->callback_data);
        $bot->deleteMessage($chat_id,$message_id);
        $status = $bot->sendMessage($chat_id,"⚙️ Fetching data...");
        $similar = getSimilarMovies($imdbId);
        $keyboard = [];
        foreach ($similar['similar'] as $item) {
            $imdb = $item['imdbId'];
            $keyboard[][] = ['text'=>$item['title']."  /  "."⭐️ ".$item['rating']."/10",'callback_data'=>"movie$imdb"];
        }
        $bot->deleteMessage($chat_id,$status->result->message_id);
        $orgTitle = $similar['orgTitle'];
        $bot->bot('sendMessage',[
            'chat_id'=>$chat_id,
            'text'=>"🔎 Movies similar <strong>$orgTitle:</strong>",
            'parse_mode'=>'html',
            'reply_markup' => json_encode([
                'inline_keyboard' =>$keyboard
            ])
        ]);
        exit();
    }else if ($bot->needle($bot->callback_data,"episodes")){
        $imdbId = str_replace("episodes","",$bot->callback_data);
        $bot->deleteMessage($chat_id,$message_id);
        $status = $bot->sendMessage($chat_id,"⚙️ Fetching data...");
        $seasons = getMovieSeasons($imdbId);
        $keyboard = [];
        foreach ($seasons as $item) {
            $season = str_replace("Season ","",$item);
            $keyboard[][] = ['text'=>$item,'callback_data'=>"season$imdbId-$season"];
        }
        $bot->deleteMessage($chat_id,$status->result->message_id);
        $bot->bot('sendMessage',[
            'chat_id'=>$chat_id,
            'text'=>"🔎 Choose Season:",
            'parse_mode'=>'html',
            'reply_markup' => json_encode([
                'inline_keyboard' =>$keyboard
            ])
        ]);
        exit();
    }else if ($bot->needle($bot->callback_data,"season")){
        $data = str_replace("season","",$bot->callback_data);
        $data = explode("-",$data);
        $imdbId = $data[0];
        $season = $data[1];
        $bot->deleteMessage($chat_id,$message_id);
        $status = $bot->sendMessage($chat_id,"⚙️ Fetching data...");
        $episodes = getMovieEpisodes($imdbId,$season);
        $keyboard = [];
        $keyboard[][] = ['text'=>"Episode",'callback_data'=>"test"];
        $keyboard[][] = ['text'=>"Rating",'callback_data'=>"test"];

        $i=1;

        foreach ($episodes['episodes'] as $item) {
            $keyboard[][] = ['text'=>$i.". ".$item['name'],'callback_data'=>"test"];
            $keyboard[][] = ['text'=>"⭐️ ".$item['rating']."/10",'callback_data'=>"test"];
            $i++;
        }
        $bot->deleteMessage($chat_id,$status->result->message_id);
        $orgTitle = $episodes['orgTitle'];
        $bot->bot('sendMessage',[
            'chat_id'=>$chat_id,
            'text'=>"🔎 Episodes of <strong>$orgTitle</strong>:",
            'parse_mode'=>'html',
            'reply_markup' => json_encode([
                'inline_keyboard' =>chunkKeyboard($keyboard,2)
            ])
        ]);
        exit();
    }else if ($bot->needle($bot->callback_data,"actorPhotos")){
        $imdbId = str_replace("actorPhotos","",$bot->callback_data);
        $bot->deleteMessage($chat_id,$message_id);
        $status = $bot->sendMessage($chat_id,"⚙️ Fetching data...");
        $actors = getMoviePhotoActor($imdbId);
        $keyboard = [];

        $i=1;

        foreach ($actors as $actor) {
            $actorName = $actor['name'];
            $keyboard[][] = ['text'=>$actorName,'callback_data'=>"actorPhoto"];
            $i++;
        }
        $bot->deleteMessage($chat_id,$status->result->message_id);
        $bot->bot('sendMessage',[
            'chat_id'=>$chat_id,
            'text'=>"🔎 Choose any actor:",
            'parse_mode'=>'html',
            'reply_markup' => json_encode([
                'inline_keyboard' =>chunkKeyboard($keyboard,3)
            ])
        ]);
        exit();
    }else if ($bot->needle($bot->callback_data,"photos")){
        $imdbId = str_replace("photos","",$bot->callback_data);
        $bot->deleteMessage($chat_id,$message_id);
        $status = $bot->sendMessage($chat_id,"⚙️ Fetching data...");
        $photos = getMoviePhotos($imdbId,1);
        $keyboard = [];

        $i=1;

        foreach ($photos as $photo) {
            $keyboard[][] = ['text'=>"Photo $i",'callback_data'=>"photo$imdbId-$photo"];
            $i++;
        }
        $bot->deleteMessage($chat_id,$status->result->message_id);
        $bot->bot('sendMessage',[
            'chat_id'=>$chat_id,
            'text'=>"🔎 Choose any one:",
            'parse_mode'=>'html',
            'reply_markup' => json_encode([
                'inline_keyboard' =>chunkKeyboard($keyboard,4)
            ])
        ]);
        exit();
    }else if ($bot->needle($bot->callback_data,"photo")){
        $data = str_replace("photo","",$bot->callback_data);
        $data = explode("-",$data);
        $imdbId = $data[0];
        $mediaId = $data[1];
        $bot->answerQuery(false,"Wait...");
        $photo = getMoviePhoto($imdbId,$mediaId);

        $bot->bot('sendPhoto',[
            'chat_id'=>$chat_id,
            'photo'=>$photo
        ]);
        exit();
    }
    exit();
}

function getMoviePhotoActor($imdbId){
    $results = [];
    $dom = new simple_html_dom();
    $dom->load_file("https://www.imdb.com/title/$imdbId/mediaindex/?ref_=tt_mi_sm");
    $i=0;
    foreach ($dom->find('#media_index_name_filters > .media_index_filter_section > li') as $item) {
        $a = $item->find('a')[0];
        $results[$i]['href'] = $a->href;
        $results[$i]['name'] = $a->plaintext;
        $i++;
    }
    return $results;
}
if ($bot->needle($bot->text,"movie")){
    $status = $bot->sendMessage($chat_id,"🔎 Searching...");
    $movie_name = str_replace("movie ","",$bot->text);
    $movie_name = str_replace("movie","",$movie_name);

    $search = searchMovie($movie_name);
    $keyboard = [];
    foreach ($search as $item) {
        $title = $item['title'];
        $imdbId = $item['imdbId'];
        $keyboard[][] = ['text'=>$title,'callback_data'=>"movie$imdbId"];
    }
    $keyboard = chunkKeyboard($keyboard,2);


    $bot->deleteMessage($chat_id,$status->result->message_id);
    $bot->bot('sendMessage',[
        'chat_id'=>$chat_id,
        'text'=>"🔎 Select one:",
        'reply_markup' => json_encode([
            'inline_keyboard' =>$keyboard
        ])
    ]);
    exit();
}
function getMoviePhoto($imdbId,$mediaId){
    $dom = new simple_html_dom();
    $dom->load_file("https://www.imdb.com/title/$imdbId/mediaviewer/$mediaId?ref_=ttmi_mi_all_sf_6");
    $image = $dom->find('[data-testid=media-viewer] > div')[4]->find('img')[0]->src;
    return $image;
}

function getMoviePhotos($imdbId,$page=1){
    $result = [];
    $dom = new simple_html_dom();
    $dom->load_file("https://www.imdb.com/title/$imdbId/mediaindex/?page=$page&ref_=tt_mi_sm");
    $i = 0;
    foreach ($dom->find('.media_index_thumb_list > a') as $photo) {
        $href = $photo->href;
        $mediaId = explode("mediaviewer/",$href)[1];
        $mediaId = explode("?ref",$mediaId)[0];
        $result[] = $mediaId;
        $i++;
    }
    return $result;
}
function chunkKeyboard($keyboard,$length){
    return array_map(function ($item){
        return array_map(function ($item2){return $item2[0];},$item);
    },array_chunk($keyboard,$length));
}
function getMovieEpisodes($imdbId,$season){
    $result = [];
    $dom = new simple_html_dom();
    $dom->load_file("https://www.imdb.com/title/$imdbId/episodes?season=$season");
    $orgTitle = $dom->find('.subpage_title_block__right-column > .parent > [itemprop=name] > a')[0]->plaintext;
    $result['orgTitle'] = $orgTitle;
    $i=0;
    foreach ($dom->find('.list.detail > .list_item') as $item) {
        $name = $item->find('[itemprop=name]')[0]->plaintext;
        $rating = $item->find('.ipl-rating-star__rating')[0]->plaintext;

        $result['episodes'][$i]['name'] = $name;
        $result['episodes'][$i]['rating'] = $rating;
        $i++;
    }
    return $result;
}

function getMovieSeasons($imdbId){
    $result = [];
    $dom = new simple_html_dom();
    $dom->load_file("https://www.imdb.com/title/$imdbId/episodes");
    $seasons = $dom->find('select#bySeason')[0];
    $i=1;
    foreach ($seasons->find('option') as $season) {
        $result[] = "Season $i";
        $i++;
    }
    return $result;
}
function getSimilarMovies($imdbId){
    $result = [];
    $dom = new simple_html_dom();
    $dom->load_file("https://www.imdb.com/title/$imdbId/?ref_=fn_al_tt_1");
    $orgTitle = $dom->find("[data-testid=hero-title-block__title]")[0]->plaintext;

    $result['orgTitle'] = $orgTitle;

    $similar = $dom->find('[data-testid=MoreLikeThis]')[0];

    $i=0;
    foreach ($similar->find('[data-testid=shoveler-items-container] > .ipc-poster-card') as $item) {
        $title = $item->find('.ipc-poster-card__title > [data-testid=title]')[0]->plaintext;
        $rating = $item->find('.ipc-rating-star-group')[0]->plaintext;
        $mImdbId = $item->find('.ipc-lockup-overlay')[0]->href;
        $mImdbId = str_replace("/title/","",$mImdbId);
        $mImdbId = explode("/",$mImdbId)[0];
        $result['similar'][$i]['title'] = $title;
        $result['similar'][$i]['rating'] = trim($rating);
        $result['similar'][$i]['imdbId'] = trim($mImdbId);
        $i++;
    }
    return $result;
}
function searchMovie($name){
    $name = urlencode($name);
    $dom = new simple_html_dom();


    $results = [];
    $dom->load_file("https://www.imdb.com/find?q=$name&ref_=nv_sr_sm");
    $i =0;
    foreach ($dom->find('.ipc-metadata-list-summary-item') as $foundMovie) {
        $image = $foundMovie->find('.ipc-image')[0]->src;
        $title = $foundMovie->find('.ipc-metadata-list-summary-item__t')[0]->plaintext;
        $href = $foundMovie->find('.ipc-metadata-list-summary-item__t')[0]->href;
        $imdbId = str_replace("/title/","",$href);
        $imdbId = explode("/",$imdbId)[0];
        if (!empty($imdbId)){
            $results[$i]['title'] = $title;
            $results[$i]['img'] = $image;
            $results[$i]['imdbId'] = $imdbId;
            $i++;
        }

    }
    return $results;
}
function getMovieDetails($imdbId){
    $dom = new simple_html_dom();
    $result = [];

    $dom->load_file("https://www.imdb.com/title/$imdbId/?ref_=fn_al_tt_1");

    $poster = $dom->find(".ipc-image")[0]->src;
    $trailer = explode("pageProps",$dom)[1];
    $trailer = explode('"video/mp4","url":',$trailer)[1];
    $trailer = explode(',"__typename"',$trailer)[0];
    $trailer = str_replace('"',"",$trailer);
    $trailer = str_replace('\u0026',"&",$trailer);
    $rating = $dom->find(".rating-bar__base-button > .ipc-button")[0]->find('span')[0]->plaintext;
    $title = $dom->find("[data-testid=hero-title-block__title]")[0]->plaintext;
    $generes = $dom->find(".ipc-chip-list--baseAlt")[0]->plaintext;
    $story = $dom->find(".sc-16ede01-2.gXUyNh")[0]->plaintext;
    $generes = explode(" ",$generes);
    $generes = array_filter($generes,function ($item){ return !empty($item); });

    $sections = $dom->find(".sc-fa02f843-0.fjLeDR > ul > li");
    $creatorSection = $sections[0];
    $starActorsSection = $sections[1];
    $result['title'] = $title;
    $result['poster'] = $poster;
    $result['trailer'] = $trailer;
    $result['rating'] = $rating;
    $result['generes'] = $generes;
    $result['story'] = html_entity_decode($story);
    $result['creators'] = getSectionItems($creatorSection) ?? [];
    $result['starActors'] = getSectionItems($starActorsSection) ?? [];

    return $result;


}
function getSectionItems($section){
    if ($section == null) return [];
    $result = [];
    foreach ($section->find(".ipc-metadata-list-item__content-container > ul > li") as $item) {
        $res = $item->find("a")[0]->plaintext;
        $result[] = $res;
    }
    return $result;
}
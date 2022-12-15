<?php
require_once "./shd.php";

header('content-type: application/json');


// echo (getMoviePhoto($_GET['imdb'],$_GET['mediaId']));
 echo json_encode(getMoviePhotoActor($_GET['imdb']));
// echo json_encode(getMoviePhotos($_GET['imdb']));
// echo json_encode(getMovieEpisodes($_GET['imdb'],$_GET['season']));
// echo json_encode(getMovieSeasons($_GET['imdb']));
// echo json_encode(getSimilarMovies($_GET['imdb']));
// echo json_encode(getMovieDetails($_GET['imdb']));
//echo json_encode(searchMovie($_GET['name']));

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
    $dom->load_file("https://www.imdb.com/title/tt5753856/episodes");
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
    $title = $dom->find(".hero-title-block__title")[0]->plaintext;

    $rating = $dom->find(".rating-bar__base-button > .ipc-button")[0]->find('span')[0]->plaintext;
    $generes = $dom->find(".ipc-chip-list--baseAlt")[0]->plaintext;
    $story = $dom->find(".sc-16ede01-2.gXUyNh")[0]->plaintext;
    $generes = explode(" ",$generes);
    $generes = array_filter($generes,function ($item){ return !empty($item); });

    $sections = $dom->find(".sc-fa02f843-0.fjLeDR > ul > li");
    $creatorSection = $sections[0];
    $starActorsSection = $sections[1];
    $result['poster'] = $poster;
    $result['title'] = $title;
    $result['trailer'] = $trailer;
    $result['rating'] = $rating;
    $result['generes'] = $generes;
    $result['story'] = html_entity_decode($story);
    $result['creators'] = getSectionItems($creatorSection);
    $result['starActors'] = getSectionItems($starActorsSection);



    return $result;


}
function getSectionItems($section){
    $result = [];
    foreach ($section->find(".ipc-metadata-list-item__content-container > ul > li") as $item) {
        $res = $item->find("a")[0]->plaintext;
        $result[] = $res;
    }
    return $result;
}
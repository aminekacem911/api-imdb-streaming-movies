<?php

namespace zvenn;

/**
 *  Class Imdb
 *
 *
 * @package zvenn/imdb-api-streaming-movies
 * @author Amine kacem
 */
class Imdb
{
  /**
   * Returns default options combined with any user options
   *
   * @param string $options
   * @return array $defaults
   */
  private function populateOptions(array $options = []): array
  {
    //  Default options
    $defaults = [
      "cache" => true,
      "category" => "all",
      "curlHeaders" => ["Accept-Language: en-US,en;q=0.5"],
      "techSpecs" => true,
    ];

    //  Merge any user options with the default ones
    foreach ($options as $key => $option) {
      $defaults[$key] = $option;
    }

    //  Return final options array
    return $defaults;
  }

  /**
   * Gets film data from IMDB. Will first search if the
   * film name is passed instead of film-id
   * @param string $filmId
   * @param array  $options
   * @return array $response
   */
  public function film(string $filmId, array $options = []): array
  {
    //  Combine user options with default ones
    $options = $this->populateOptions($options);

    //  Initiate response object
    // -> handles what the api returns
    $response = new Response();

    //  Initiate cache object
    // -> handles storing/retrieving cached results
    $cache = new Cache();

    //  Initiate dom object
    //  -> handles page scraping
    $dom = new Dom();

    //  Initiate html-pieces object
    //  -> handles finding specific content from the dom
    $htmlPieces = new HtmlPieces();

    // Check for 'tt' at start of $filmId
    if (substr($filmId, 0, 2) !== "tt") {
      //  Search $filmId and use first result
      $search_film = $this->search($filmId, ["category" => "tt"]);
      if ($htmlPieces->count($search_film["titles"]) > 0) {
        // Use first film returned from search
        $filmId = $search_film["titles"][0]["id"];
      } else {
        //  No film found
        //  -> return default (empty) response
        return $response->default("film");
      }
    }

    //  If caching is enabled
    if ($options["cache"]) {
      //  Check cache for film
      if ($cache->has($filmId)) {
        return $cache->get($filmId)->film;
      }
    }

    //  Load imdb film page and parse the dom
    $page = $dom->fetch("https://www.imdb.com/title/" . $filmId, $options);

    //  Add all film data to response $store
    $response->add("id", $filmId);
    $response->add("title", $htmlPieces->get($page, "title"));
    $response->add("year", $htmlPieces->get($page, "year"));
    $response->add("length", $htmlPieces->get($page, "length"));
    $response->add("plot", $htmlPieces->get($page, "plot"));
    $response->add("rating", $htmlPieces->get($page, "rating"));
    $response->add("rating_votes", $htmlPieces->get($page, "rating_votes"));
    $response->add("poster", $htmlPieces->get($page, "poster"));
    $response->add("trailer", $htmlPieces->get($page, "trailer"));
    $response->add("cast", $htmlPieces->get($page, "cast"));

    //  If techSpecs is enabled in user $options
    //  -> Make a second request to load the full techSpecs page
    if ($options["techSpecs"]) {
      $page_techSpecs = $dom->fetch(
        "https://www.imdb.com/title/$filmId/technical",
        $options
      );
      $response->add(
        "technical_specs",
        $htmlPieces->get($page_techSpecs, "technical_specs")
      );
    } else {
      $response->add("technical_specs", []);
    }

    //  If caching is enabled
    if ($options["cache"]) {
      //  Add result to the cache
      $cache->add($filmId, $response->return());
    }

    //  Return the response $store
    return $response->return();
  }

  /**
   * Searches IMDB for films, people and companies
   * @param string $search
   * @param array  $options
   * @return array $searchData
   */
  public function search(string $search, array $options = []): array
  {
    //  Combine user options with default ones
    $options = $this->populateOptions($options);

    //  Initiate response object
    // -> handles what the api returns
    $response = new Response();

    //  Initiate dom object
    //  -> handles page scraping
    $dom = new Dom();

    //  Initiate html-pieces object
    //  -> handles finding specific content from the dom
    $htmlPieces = new HtmlPieces();

    //  Encode search string as a standard URL string
    //  -> ' ' => '%20'
    $search_url = urlencode(urldecode($search));

    //  Load imdb search page and parse the dom
    $page = $dom->fetch(
      "https://www.imdb.com/find?q=$search_url&s=" . $options["category"],
      $options
    );

    //time for search fillm via api daily movies
    //get the basic href

    $url = "https://www.dailymotion.com/embed/video/";
    //need to bypass forbidden with adding fake header (user-agent)

    $curl = curl_init();

    curl_setopt_array($curl, [
      CURLOPT_URL => "https://api.dailymotion.com/videos?search=$search_url&fields=id&country=us",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "GET",
      CURLOPT_POSTFIELDS => "",
      CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Accept: application/json",
        "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.95 Safari/53",
      ],
    ]);

    $data = curl_exec($curl);

    $stack = [];
    foreach ($data->list as $val) {
      $full = $url . $val->id;

      array_push($stack, $full);
    }
    $response->add("movies", $stack);
    //  Add all search data to response $store
    $response->add("titles", $htmlPieces->get($page, "titles"));
    $response->add("names", $htmlPieces->get($page, "names"));
    $response->add("companies", $htmlPieces->get($page, "companies"));

    return $response->return();
  }
}

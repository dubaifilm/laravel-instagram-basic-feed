<?php

namespace ChillPills\InstagramBasicFeed;
use EspressoDev\InstagramBasicDisplay\InstagramBasicDisplay;
use Illuminate\Support\Facades\Cache;

class InstagramBasicFeed
{
    protected $medias;
    protected $instagramBasicDisplay;
    protected $userMedia;
    public static $cacheKey;
    public function __construct()
    {
      $accessToken = config('instagram-basic-feed.access_token');

      $this->instagramBasicDisplay = new InstagramBasicDisplay($accessToken);

      $this->userMedia = null;
      $this->medias = [];

      self::$cacheKey = config('instagram-basic-feed.cache_key');
        $this->medias = Cache::get(InstagramFeedCrawler::$cacheKey, []);
    }

    public function getUserMedias()
    {
        return $this->medias;
    }

    public function getUserMediasWithHashtag($hashtag)
    {
        if (empty($hashtag)) {
            return $this->getUserMedias();
        }

        $mediaMatch = [];

        foreach ($this->medias as $media) {
            if (isset($media->caption) && strpos($media->caption, $hashtag) !== false) {
                $mediaMatch[] = $media;
            }
        }

        return $mediaMatch;
    }

    public function getUserMedia($before = null, $after = null)
    {
        if ($this->userMedia == null) {
            $this->userMedia = $this->instagramBasicDisplay->getUserMedia('me', 0, $before, $after);
        }

        return $this->userMedia;
    }

    public function getAllUserMedias()
    {
        if (count($this->medias)) {
            return $this->medias;
        }

        $before = null;

        do {
            $this->userMedia = $this->getUserMedia($before);

            $this->medias = array_merge($this->medias, $this->userMedia->data);

            if (isset($this->userMedia->paging->cursors->before) && $before != $this->userMedia->paging->cursors->before) {
                $before = $this->userMedia->paging->cursors->before;
            } else {
                $before = null;
            }
        } while ($before != null);

        $this->removeDuplicatePosts();

        return $this->medias;
    }

    protected function removeDuplicatePosts()
    {
        $finalMedias = [];

        foreach ($this->medias as $media) {
            if (! array_key_exists($media->id, $finalMedias)) {
                $finalMedias[$media->id] = $media;
            }
        }

        $this->medias = $finalMedias;
    }

    public function fetchNewFeed()
    {
        $this->medias = $this->getAllUserMedias();

        $this->storeNewFeedInCache();

        return $this->medias;
    }

    protected function storeNewFeedInCache()
    {
        Cache::put(self::$cacheKey, $this->medias);
    }
  }

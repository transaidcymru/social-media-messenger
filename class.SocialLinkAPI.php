<?php

error_log("this is actually being required_once");

// Base class for social media API implementations (e.g. Facebook, BlueSky etc.).
class SocialLinkAPI {

    function __construct(){

    }

    // TODO: Dummy implementation
    public function getMaxFetch() {
        return 0;
    }
}

?>


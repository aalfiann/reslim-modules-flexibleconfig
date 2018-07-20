<?php
//Define interface class for router
use \Psr\Http\Message\ServerRequestInterface as Request;        //PSR7 ServerRequestInterface   >> Each router file must contains this
use \Psr\Http\Message\ResponseInterface as Response;            //PSR7 ResponseInterface        >> Each router file must contains this

//Define your modules class
use \modules\flexibleconfig\FlexibleConfig as FlexibleConfig;   //Your main modules class

//Define additional class for any purpose
use \classes\middleware\ValidateParam as ValidateParam;         //ValidateParam                 >> To validate the body form request  
use \classes\middleware\ValidateParamURL as ValidateParamURL;   //ValidateParamURL              >> To validate the query parameter url
use \classes\middleware\ApiKey as ApiKey;                       //ApiKey Middleware             >> To authorize request by using ApiKey generated by reSlim
use \classes\SimpleCache as SimpleCache;                        //SimpleCache class             >> To cache response ouput server side


    // Get module information
    $app->map(['GET','OPTIONS'],'/flexibleconfig/get/info/', function (Request $request, Response $response) {
        $fc = new FlexibleConfig($this->db);
        $body = $response->getBody();
        $response = $this->cache->withEtag($response, $this->etag2hour.'-'.trim($_SERVER['REQUEST_URI'],'/'));
        $body->write($fc->viewInfo());
        return classes\Cors::modify($response,$body,200,$request);
    })->add(new ApiKey);


    //FlexibleConfig======================================================


    // POST api to add new config
    $app->post('/flexibleconfig/add', function (Request $request, Response $response) {
        $fc = new FlexibleConfig($this->db);
        $fc->lang = (empty($_GET['lang'])?$this->settings['language']:$_GET['lang']);
        $datapost = $request->getParsedBody();
        $fc->username = $datapost['Username'];
        $fc->token = $datapost['Token'];
        $fc->key = $datapost['Key'];
        $fc->value = $datapost['Value'];
        $fc->description = $datapost['Description'];
        $body = $response->getBody();
        $body->write($fc->add());
        return classes\Cors::modify($response,$body,200);
    })->add(new ValidateParam('Description','0-250'))
        ->add(new ValidateParam('Value','0-10000'))
        ->add(new ValidateParam('Token','1-250','required'))
        ->add(new ValidateParam(['Username','Key'],'1-50','required'));


    // POST api to update config
    $app->post('/flexibleconfig/update', function (Request $request, Response $response) {
        $fc = new FlexibleConfig($this->db);
        $fc->lang = (empty($_GET['lang'])?$this->settings['language']:$_GET['lang']);
        $datapost = $request->getParsedBody();    
        $fc->username = $datapost['Username'];
        $fc->token = $datapost['Token'];
        $fc->value = $datapost['Value'];
        $fc->description = $datapost['Description'];
        $fc->key = $datapost['Key'];
        $body = $response->getBody();
        $body->write($fc->update());
        return classes\Cors::modify($response,$body,200);
    })->add(new ValidateParam('Description','0-250'))
        ->add(new ValidateParam('Value','0-10000'))
        ->add(new ValidateParam('Token','1-250','required'))
        ->add(new ValidateParam(['Username','Key'],'1-50','required'));


    // POST api to delete config
    $app->post('/flexibleconfig/delete', function (Request $request, Response $response) {
        $fc = new FlexibleConfig($this->db);
        $fc->lang = (empty($_GET['lang'])?$this->settings['language']:$_GET['lang']);
        $datapost = $request->getParsedBody();    
        $fc->key = $datapost['Key'];
        $fc->username = $datapost['Username'];
        $fc->token = $datapost['Token'];
        $body = $response->getBody();
        $body->write($fc->delete());
        return classes\Cors::modify($response,$body,200);
    })->add(new ValidateParam('Token','1-250','required'))
        ->add(new ValidateParam(['Username','Key'],'1-50','required'));


    // GET api to show all data config (index) with pagination server side
    $app->get('/flexibleconfig/index/{username}/{token}/{page}/{itemsperpage}/', function (Request $request, Response $response) {
        $fc = new FlexibleConfig($this->db);
        $fc->lang = (empty($_GET['lang'])?$this->settings['language']:$_GET['lang']);
        $fc->search = filter_var((empty($_GET['query'])?'':$_GET['query']),FILTER_SANITIZE_STRING);
        $fc->username = $request->getAttribute('username');
        $fc->token = $request->getAttribute('token');
        $fc->page = $request->getAttribute('page');
        $fc->itemsPerPage = $request->getAttribute('itemsperpage');
        $body = $response->getBody();
        $body->write($fc->index());
        return classes\Cors::modify($response,$body,200);
    })->add(new ValidateParamURL('query'));


    // GET api to read single data
    $app->get('/flexibleconfig/read/{key}/{username}/{token}', function (Request $request, Response $response) {
        $fc = new FlexibleConfig($this->db);
        $fc->lang = (empty($_GET['lang'])?$this->settings['language']:$_GET['lang']);
        $fc->username = $request->getAttribute('username');
        $fc->token = $request->getAttribute('token');
        $fc->key = $request->getAttribute('key');
        $body = $response->getBody();
        $body->write($fc->read());
        return classes\Cors::modify($response,$body,200);
    });

    
    // GET api to read single data for public user (include cache)
    $app->get('/flexibleconfig/read/{key}/', function (Request $request, Response $response) {
        $fc = new FlexibleConfig($this->db);
        $fc->lang = (empty($_GET['lang'])?$this->settings['language']:$_GET['lang']);
        $fc->key = $request->getAttribute('key');
        $body = $response->getBody();
        $response = $this->cache->withEtag($response, $this->etag.'-'.trim($_SERVER['REQUEST_URI'],'/'));
        if (SimpleCache::isCached(300,["apikey","lang"])){
            $datajson = SimpleCache::load(["apikey","lang"]);
        } else {
            $datajson = SimpleCache::save($fc->readPublic(),["apikey","lang"]);
        }
        $body->write($datajson);
        return classes\Cors::modify($response,$body,200);
    })->add(new ValidateParamURL('lang','0-2'))->add(new ApiKey);


    // GET api to test get value by key
    $app->get('/flexibleconfig/test/{key}', function (Request $request, Response $response) {
        $fc = new FlexibleConfig($this->db);
        $body = $response->getBody();
        $body->write('{"result":"'.$fc->readConfig($request->getAttribute('key')).'"}');
        return classes\Cors::modify($response,$body,200);
    });
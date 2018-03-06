<?php

require_once './imap_client.php';
require_once './view.php';


abstract class Controller {

    /**
     * @var ViewHandler
     */
    protected $viewHandler;

    public function setViewHandler(ViewHandler $outputHandler) {
        $this->viewHandler = $outputHandler;
    }

    function invoke(ImapClient $imapClient) {
    }

    function if_invalid_redirect_to_random(User $user, array $config_domains) {
        if ($user->isInvalid($config_domains)) {
            $this->redirect_to_random($config_domains);
            exit();
        }
    }

    function redirect_to_random(array $domains) {
        $wordLength = rand(3, 8);
        $container = new PronounceableWord_DependencyInjectionContainer();
        $generator = $container->getGenerator();
        $word = $generator->generateWordOfGivenLength($wordLength);
        $nr = rand(51, 91);
        $name = $word . $nr;

        $domain = $domains[array_rand($domains)];
        $this->viewHandler->newAddress("$name@$domain");
    }

}

class RedirectToAddressController extends Controller {
    private $username;
    private $domain;
    private $config_blocked_usernames;

    public function __construct(string $username, string $domain, array $config_blocked_usernames) {
        $this->username = $username;
        $this->domain = $domain;
        $this->config_blocked_usernames = $config_blocked_usernames;
    }

    function invoke(ImapClient $imapClient) {
        $user = User::parseUsernameAndDomain($this->username, $this->domain, $this->config_blocked_usernames);
        $this->viewHandler->newAddress($user->username . "@" . $user->domain);
    }
}

class DownloadEmailController extends Controller {

    private $email_id;
    private $address;
    private $config_domains;
    private $config_blocked_usernames;

    public function __construct(string $email_id, string $address, array $config_domains, array $config_blocked_usernames) {
        $this->email_id = $email_id;
        $this->address = $address;
        $this->config_domains = $config_domains;
        $this->config_blocked_usernames = $config_blocked_usernames;
    }


    function invoke(ImapClient $imapClient) {
        $user = User::parseDomain($this->address, $this->config_blocked_usernames);
        $this->if_invalid_redirect_to_random($user, $this->config_domains);

        $download_email_id = filter_var($this->email_id, FILTER_SANITIZE_NUMBER_INT);
        $full_email = $imapClient->load_one_email_fully($download_email_id, $user);
        if ($full_email !== null) {
            $filename = $user->address . "-" . $download_email_id . ".eml";
            $this->viewHandler->downloadEmailAsRfc822($full_email, $filename);
        } else {
            $this->viewHandler->error(404, 'download error: invalid username/mailid combination');
        }
    }
}


class DeleteEmailController extends Controller {
    private $email_id;
    private $address;
    private $config_domains;
    private $config_blocked_usernames;

    public function __construct($email_id, $address, $config_domains, array $config_blocked_usernames) {
        $this->email_id = $email_id;
        $this->address = $address;
        $this->config_domains = $config_domains;
        $this->config_blocked_usernames = $config_blocked_usernames;
    }

    function invoke(ImapClient $imapClient) {
        $user = User::parseDomain($this->address, $this->config_blocked_usernames);
        $this->if_invalid_redirect_to_random($user, $this->config_domains);

        $delete_email_id = filter_var($this->email_id, FILTER_SANITIZE_NUMBER_INT);
        if ($imapClient->delete_email($delete_email_id, $user)) {
            $this->viewHandler->done($this->address);
        } else {
            $this->viewHandler->error(404, 'delete error: invalid username/mailid combination');
        }
    }
}

class RedirectToRandomAddressController extends Controller {
    private $config_domains;

    public function __construct($config_domains) {
        $this->config_domains = $config_domains;
    }

    function invoke(ImapClient $imapClient) {
        $this->redirect_to_random($this->config_domains);
    }

}

class DisplayEmailsController extends Controller {
    private $address;
    private $config;

    public function __construct($address, $config) {
        $this->address = $address;
        $this->config = $config;
    }


    function invoke(ImapClient $imapClient) {
        // print emails with html template
        $user = User::parseDomain($this->address, $this->config['blocked_usernames']);
        $this->if_invalid_redirect_to_random($user, $this->config['domains']);
        $emails = $imapClient->get_emails($user);

        $this->viewHandler->displayEmails($emails, $this->config, $user);
    }
}

class InvalidRequestController extends Controller {
    function invoke(ImapClient $imapClient) {
        $this->viewHandler->error(400, "Bad Request");
    }
}
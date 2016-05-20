<?php

/**
 * AkkaFacebook Graph Component
 * 
 * @author Andre Santiago
 * @copyright (c) 2015 akkaweb.com
 * @license MIT
 */

namespace AkkaFacebook\Controller\Component;

use Cake\Controller\Component;
use Cake\Controller\ComponentRegistry;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\ORM\TableRegistry;
use Cake\Routing\Router;
use Facebook;

/**
 * Graph component
 */
class GraphComponent extends Component {

	/**
	 * Facebook Main Class
	 * 
	 * @var type type Object
	 */
	public $Facebook = null;

	/**
	 * 	Facebook Redirect Login Helper
	 * 
	 * @var type Object
	 */
	public $FacebookHelper = null;

	/**
	 * Facebook Access Token
	 * 
	 * @var type String
	 */
	public $FacebookAccessToken = null;

	/**
	 * Assigned Redirect Url 
	 * 
	 * @var type String
	 */
	public $FacebookRedirectUrl = null;

	/**
	 * Facebook Request
	 * 
	 * @var type Object
	 */
	public $FacebookRequest = null;

	/**
	 * Facebook Response
	 * 
	 * @var type Object
	 */
	public $FacebookResponse = null;

	/**
	 * Facebook Graph User
	 * 
	 * @var type Object
	 */
	public $FacebookGraphUser = null;

	/**
	 * Facebook User Full Name
	 * 
	 * @var type String
	 */
	public $FacebookName = null;

	/**
	 * Facebook User First Name
	 * 
	 * @var type String
	 */
	public $FacebookFirstName = null;

	/**
	 * Facebook User Last Name
	 * 
	 * @var type String
	 */
	public $FacebookLastName = null;

	/**
	 * Facebook User Id
	 * 
	 * @var type String
	 */
	public $FacebookId = null;

	/**
	 * Facebook User Email
	 * 
	 * @var type String
	 */
	public $FacebookEmail = null;
	public $FacebookPicture = null;

	/**
	 * Component Configuration
	 * 
	 * @var type Array
	 */
	protected $_configs = null;

	/**
	 * Application Users Model Object
	 * 
	 * @var type Object
	 */
	protected $Users = null;

	/**
	 * Components Controller
	 * 
	 * @var type Object
	 */
	protected $Controller = null;

	/**
	 * Application Components
	 * 
	 * @var type Component
	 */
	public $components = ['Flash', 'Auth'];

	/**
	 * Default configuration.
	 *
	 * @var array
	 */
	protected $_defaultConfig = [
		'app_id' => '',
		'app_secret' => '',
		'app_scope' => [],
		'default_graph_version' => 'v2.5',
		'redirect_url' => '/users/login',
		'post_login_redirect' => '/',
		'enable_graph_helper' => true,
		'user_model' => 'Users',
		'user_columns' => [
			'first_name' => 'first_name',
			'last_name' => 'last_name',
			'password' => 'password',
			'username' => 'username',
			'avatar' => 'avatar',
			'gender' => 'gender'
		]
	];

	/**
	 * Initialize Controllers, User Model and Session
	 * 
	 * @param array $config
	 */
	public function initialize(array $config) {
		parent::initialize($config);
		/**
		 * Assigned merge configuration
		 */
		$this->_configs = $this->config();

		/**
		 * Get current controller
		 */
		$this->Controller = $this->_registry->getController();
		//debug($this->Controller->request);
		/**
		 * Start session if not already started
		 */
		if ($this->isSessionStarted() === FALSE) {
			$this->Controller->request->session()->start();
		}

		/**
		 * Attach Facebook Graph Helper
		 */
		if ($this->_configs['enable_graph_helper']) {
			$this->Controller->helpers = [
				'AkkaFacebook.Facebook' => [
					'redirect_url' => $this->_configs['redirect_url'],
					'app_id' => $this->_configs['app_id'],
					'app_scope' => $this->_configs['app_scope']
				]
			];
		}

		/**
		 * Initialize the Users Model class
		 */
		$this->Users = TableRegistry::get($this->_configs['user_model']);
		$this->Users->recursive = -1;
	}

	/**
	 * Initialize Facebook, create Session, fire Request and get User Object
	 * 
	 * @param \Cake\Event\Event $event
	 */
	public function beforeFilter(Event $event) {

		$this->Facebook = new Facebook\Facebook([
			'app_id' => $this->_configs['app_id'],
			'app_secret' => $this->_configs['app_secret'],
			'default_graph_version' => $this->_configs['default_graph_version'],
		]);

		$this->FacebookRedirectUrl = $this->_configs['redirect_url'];
		$this->FacebookHelper = $this->Facebook->getRedirectLoginHelper();

		try {
			$this->FacebookAccessToken = $this->FacebookHelper->getAccessToken();
		} catch (Facebook\Exceptions\FacebookResponseException $e) {
			// When Graph returns an error
			$this->log($e->getMessage());
		} catch (Facebook\Exceptions\FacebookSDKException $e) {
			// When validation fails or other local issues
			$this->log($e->getMessage());
		} catch (Exception $e) {
			$this->log($e->getMessage());
		}

		if ($this->FacebookAccessToken) {
			try {
				$oAuth2Client = $this->Facebook->getOAuth2Client();
				$longLivedAccessToken = $oAuth2Client->getLongLivedAccessToken($this->FacebookAccessToken);

				$this->Facebook->setDefaultAccessToken($longLivedAccessToken);

				$this->FacebookResponse = $this->Facebook->get('/me?fields=name,email,first_name,last_name,gender,picture.type(large)');
				$this->FacebookGraphUser = $this->FacebookResponse->getGraphUser();

				$this->FacebookName = $this->FacebookGraphUser->getName();
				$this->FacebookFirstName = $this->FacebookGraphUser->getFirstName();
				$this->FacebookLastName = $this->FacebookGraphUser->getLastName();
				$this->FacebookEmail = $this->FacebookGraphUser->getEmail();
				$this->FacebookId = $this->FacebookGraphUser->getId();
				$this->Gender = $this->FacebookGraphUser->getGender();
				
				$picture = $this->FacebookGraphUser->getPicture();
				$this->FacebookPicture = $picture->getUrl();
			} catch (Facebook\Exceptions\FacebookResponseException $e) {
				// When Graph returns an error
				$this->log($e->getMessage());
			} catch (Facebook\Exceptions\FacebookSDKException $e) {
				// When validation fails or other local issues
				$this->log($e->getMessage());
			} catch (Exception $e) {
				$this->log($e->getMessage());
			}
		}
	}

	/**
	 *  Component Startup
	 * 
	 * @param \Cake\Event\Event $event
	 */
	public function startup(Event $event) {
		/**
		 * Checks if user is trying to authenticate by watching for what Facebook returns
		 */
		//debug($this->Controller->request->query('code'));
		if ($this->Controller->request->query('code')) {

			// Redirect url comes from Auth
			$this->_configs['post_login_redirect'] = $this->Auth->redirectUrl();

			//debug($this->Controller->request);die;
			/**
			 * Queries database for existing Facebook Id
			 */
			$queryFacebookId = $this->Users->find('all')->where(['facebook_id' => $this->FacebookId])->first();

			/**
			 * Authenticates existing user into application
			 */
			if ($queryFacebookId) {

				$existing_user = $queryFacebookId->toArray();
				if ($this->Auth->user() && $this->Auth->user('facebook_id') != $existing_user['facebook_id']) {
					$this->Flash->set('Dit Facebook account is al gekoppeld aan een ander e-mail adres.', array('element' => 'Site/error'));
					$this->Controller->redirect($this->_configs['post_login_redirect']);
				} else {
					$this->__updatePicture($queryFacebookId);
					$existing_user[$this->_configs['user_columns']['avatar']] = $this->FacebookPicture;
					$this->Auth->setUser($existing_user);
					$this->Controller->redirect($this->_configs['post_login_redirect']);
				}
			} else {
				/**
				 * Queries database for existing user based on Email
				 */
				$queryFacebookEmail = $this->Users->find('all')->where(['email' => $this->FacebookEmail])->first();


				/**
				 * Updates user account by adding FacebookId to it and authenticates user
				 */
				if ($queryFacebookEmail) {
					if ($this->Auth->user() && $this->Auth->user('email') != $queryFacebookEmail['email']) {
						$this->Flash->set('Dit Facebook account is al gekoppeld aan een ander e-mail adres.', array('element' => 'Site/error'));
						$this->Controller->redirect($this->_configs['post_login_redirect']);
					} else {
						$this->__updateAccount($queryFacebookEmail);
					}
				} else {
					/**
					 * If user is already logged in... add to their logged in account
					 */
					if ($this->Auth->user()) {
						$user = $this->Users->get($this->Auth->user('id'));
						$this->__updateAccount($user);
					} else {
						/**
						 * If FacebookUserId and FacebookUserEmail is not in database, create new account
						 */
						$newAccountResult = $this->__newAccount();
						if(empty($newAccountResult['status'])){
							$this->Flash->set($newAccountResult['message'], array('element' => 'Site/error'));
							$this->Controller->redirect($this->_configs['post_login_redirect']);
						}
					}
				}
			}
		} else if ($this->Controller->request->query('error')) {
			//$this->Flash->set('Yikes! Something went wrong, or maybe you just didn\'t login with Facebook!', array('element' => 'Site/error'));
			$this->Controller->redirect('/');
		}
	}

	/**
	 *  Component Before Render 
	 * 
	 * @param \Cake\Event\Event $event
	 */
	public function beforeRender(Event $event) {
		/**
		 * Sets/Configures fb_login_url to be assigned in Facebook Login Button
		 */
		$loginUrl = $this->FacebookHelper->getLoginUrl($this->_configs['redirect_url'], [$this->_configs['app_scope']]);

		$this->Controller->set('fb_login_url', $loginUrl);
		Configure::write('fb_login_url', $loginUrl);
	}

	/**
	 * Add facebook_id to existing user based on their email
	 * @param type $user
	 */
	protected function __updateAccount($user) {
		$this->Users->patchEntity($user, ['facebook_id' => $this->FacebookId, $this->_configs['user_columns']['avatar'] => $this->FacebookPicture]);
		if ($result = $this->Users->save($user)) {
			$this->__autoLogin($result);
		}
	}

	protected function __updatePicture($user) {
		$this->Users->patchEntity($user, [$this->_configs['user_columns']['avatar'] => $this->FacebookPicture]);
		$result = $this->Users->save($user);
	}

	/**
	 * Create a new user using Facebook Credentials
	 */
	protected function __newAccount() {
		$data = [
			//$this->_configs['user_columns']['username'] => $this->__generateUsername(),
			$this->_configs['user_columns']['first_name'] => $this->FacebookFirstName,
			$this->_configs['user_columns']['last_name'] => $this->FacebookLastName,
			$this->_configs['user_columns']['password'] => $this->__randomPassword(),
			$this->_configs['user_columns']['avatar'] => $this->FacebookPicture,
			$this->_configs['user_columns']['gender'] => $this->Gender,
			'facebook_id' => $this->FacebookId,
			'email' => $this->FacebookEmail
		];
		
		if(!empty($this->FacebookEmail)){
			$user = $this->Users->newEntity($data);

			$result = $this->Users->save($user);
			if ($result) {
				$this->__autoLogin($result, true);
			}
			return ['status' => true, 'message' => ''];
		}else{
			return ['status' => false, 'message' => 'Email can not be empty'];
		}
	}

	/**
	 * Logs user in application after successful save
	 * 
	 * @param type $result
	 */
	protected function __autoLogin($result, $new_user = false) {
		$authUser = $this->Users->get($result->id)->toArray();
		
		$redirectUrl = ['controller' => 'Users', 'action' => 'profile'];
		if(strpos($this->_configs['post_login_redirect'], '/invite') !== false){
			$redirectUrl = $this->_configs['post_login_redirect'];
		}

		$this->Auth->setUser($authUser);
		if ($new_user) {
			$this->Controller->redirect($redirectUrl);
		} else {
			$this->Controller->redirect($this->_configs['post_login_redirect']);
		}
	}

	/**
	 * Creates a new username
	 * 
	 * @return type String
	 */
	protected function __generateUsername() {
		$username = strtolower($this->FacebookFirstName . $this->FacebookLastName);

		while ($this->Users->find()->where([$this->_configs['user_columns']['username'] => $username])->first()) {
			$username = $username . rand(0, 900);
		}

		return $username;
	}

	/**
	 * Generate a random password
	 * 
	 * @return type String
	 */
	protected function __randomPassword() {
		$alphabet = "abcdefghijklmnopqrstuwxyzABCDEFGHIJKLMNOPQRSTUWXYZ0123456789";
		$pass = array(); //remember to declare $pass as an array
		$alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
		for ($i = 0; $i < 8; $i++) {
			$n = rand(0, $alphaLength);
			$pass[] = $alphabet[$n];
		}
		return implode($pass); //turn the array into a string        
	}

	/**
	 * @return bool
	 */
	public function isSessionStarted() {
		if (php_sapi_name() !== 'cli') {
			if (version_compare(phpversion(), '5.4.0', '>=')) {
				return session_status() === PHP_SESSION_ACTIVE ? TRUE : FALSE;
			} else {
				return session_id() === '' ? FALSE : TRUE;
			}
		}
		return FALSE;
	}

}

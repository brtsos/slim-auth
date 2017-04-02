<?php

namespace App\Controllers\Auth;

use App\Models\User;
use App\Controllers\Controller;
use Respect\Validation\Validator as v;
use Nette\Mail\Message;

class AuthController extends Controller
{
	public function getSignOut($request,$response)
	{
		$this->auth->logout();
		return $response->withRedirect($this->router->pathFor('home'));
	}

	public function getSignIn($request,$response)
	{
		return $this->view->render($response,'auth/signin.twig');
	}

	public function postSignIn($request,$response)
	{
		$auth = $this->auth->attempt(
			$request->getParam('email'),
			$request->getParam('password')
		);

		if (!$auth) {
			$this->flash->addMessage('error','Could not sign you in with those details');
			return $response->withRedirect($this->router->pathFor('auth.signin'));
		}

		return $response->withRedirect($this->router->pathFor('home'));
	}

	public function getSignUp($request,$response)
	{
		return $this->view->render($response,'auth/signup.twig');
	}

	public function postSignUp($request,$response)
	{

		$validation = $this->validator->validate($request,[
			'email' => v::noWhitespace()->notEmpty()->email()->emailAvailable(),
			'name' => v::notEmpty()->alpha(),
			'password' => v::noWhitespace()->notEmpty(),
		]);

		if ($validation->failed()) {
			return $response->withRedirect($this->router->pathFor('auth.signup'));
		}

		$activCode = md5('yourSalt' . date('Ymdhis'));
		
		$user = User::create([
			'email' => $request->getParam('email'),
			'name' => $request->getParam('name'),
			'password' => password_hash($request->getParam('password'),PASSWORD_DEFAULT),
			'activ_code' => $activCode
		]);
		
		$mail = new Message;
		$mail->setFrom('your@email.com')
			->addTo($request->getParam('email'))
			->setSubject('Plaease confirm your email')
			->setHTMLBody("Hello, to confirm this Email click this URL: <br />
			<a target='_blank' href='" . $this->container->settings['baseUrl'] . "auth/confirm?code=" . $activCode ."'>
			" . $this->container->settings['baseUrl'] . "/auth/confirm?code=" . $activCode . "</a>");
		
		$this->mailer->send($mail);

		$this->flash->addMessage('info','Please confirm your email. We send a Email with activate Code.');

		//$this->auth->attempt($user->email,$request->getParam('password')); // ← we don't need auto login anymore

		return $response->withRedirect($this->router->pathFor('home'));

	}
	
	public function confirmEmail($request,$response)
	{
		
		if (!$request->getParam('code')) {
			return $response->withRedirect($this->router->pathFor('home'));
		} 

		$user = User::where('activ_code', $request->getParam('code'))->first();
		$user->activ = 1;
		$user->save();
		
		$this->flash->addMessage('info','Congratulation! Your email is confimed. You can sing on now.');

		return $this->view->render($response,'auth/signin.twig');
	}
	
}
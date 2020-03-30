<?php
namespace OCA\Wiki\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Controller;

class WikiController extends Controller {
	public function __construct($AppName, IRequest $request){
		parent::__construct($AppName, $request);
	}

	/**
	 * CAUTION: the @Stuff turns off security checks; for this page no admin is
	 *          required and no CSRF check. If you don't know what CSRF is, read
	 *          it up in the docs or you might create a security hole. This is
	 *          basically the only required method to add this exemption, don't
	 *          add it to any other method if you don't exactly know what it does
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @return TemplateResponse
	 */
	public function index(): TemplateResponse {
		return new TemplateResponse('wiki', 'main');  // templates/main.php
	}

	public function show() {
		// to be implemented
	}

}

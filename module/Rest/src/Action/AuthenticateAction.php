<?php
declare(strict_types=1);

namespace Shlinkio\Shlink\Rest\Action;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Log\LoggerInterface;
use Shlinkio\Shlink\Rest\Authentication\JWTServiceInterface;
use Shlinkio\Shlink\Rest\Service\ApiKeyService;
use Shlinkio\Shlink\Rest\Service\ApiKeyServiceInterface;
use Shlinkio\Shlink\Rest\Util\RestUtils;
use Zend\Diactoros\Response\JsonResponse;
use Zend\I18n\Translator\TranslatorInterface;

class AuthenticateAction extends AbstractRestAction
{
    /**
     * @var TranslatorInterface
     */
    private $translator;
    /**
     * @var ApiKeyService|ApiKeyServiceInterface
     */
    private $apiKeyService;
    /**
     * @var JWTServiceInterface
     */
    private $jwtService;

    public function __construct(
        ApiKeyServiceInterface $apiKeyService,
        JWTServiceInterface $jwtService,
        TranslatorInterface $translator,
        LoggerInterface $logger = null
    ) {
        parent::__construct($logger);
        $this->translator = $translator;
        $this->apiKeyService = $apiKeyService;
        $this->jwtService = $jwtService;
    }

    /**
     * @param Request $request
     * @return Response
     * @throws \InvalidArgumentException
     */
    public function handle(Request $request): Response
    {
        $authData = $request->getParsedBody();
        if (! isset($authData['apiKey'])) {
            return new JsonResponse([
                'error' => RestUtils::INVALID_ARGUMENT_ERROR,
                'message' => $this->translator->translate(
                    'You have to provide a valid API key under the "apiKey" param name.'
                ),
            ], self::STATUS_BAD_REQUEST);
        }

        // Authenticate using provided API key
        $apiKey = $this->apiKeyService->getByKey($authData['apiKey']);
        if ($apiKey === null || ! $apiKey->isValid()) {
            return new JsonResponse([
                'error' => RestUtils::INVALID_API_KEY_ERROR,
                'message' => $this->translator->translate('Provided API key does not exist or is invalid.'),
            ], self::STATUS_UNAUTHORIZED);
        }

        // Generate a JSON Web Token that will be used for authorization in next requests
        $token = $this->jwtService->create($apiKey);
        return new JsonResponse(['token' => $token]);
    }
}

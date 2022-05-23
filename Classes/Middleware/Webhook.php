<?php

namespace Extcode\CartPayrexx\Middleware;

/*
 * This file is part of the package extcode/cart-payrexx.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Model\Order\Item;
use Extcode\Cart\Domain\Model\Order\Payment;
use Extcode\Cart\Domain\Repository\CartRepository;
use Extcode\Cart\Domain\Repository\Order\ItemRepository;
use Extcode\Cart\Domain\Repository\Order\PaymentRepository;
use Extcode\Cart\Domain\Repository\Order\TransactionRepository;
use Extcode\CartPayrexx\Event\Order\FinishEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

class Webhook implements MiddlewareInterface
{
    /**
     * @var ResponseFactoryInterface
     */
    private $responseFactory;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        if (array_key_exists('webhook', $queryParams) &&
            $queryParams['webhook'] === 'cart-payrexx-webhook'
        ) {
            $hash = $_POST['transaction']['invoice']['paymentLink']['hash'];
            $status = $_POST['transaction']['status'];

            $transactionRepository = GeneralUtility::makeInstance(TransactionRepository::class);
            $querySettings = $transactionRepository->createQuery()->getQuerySettings();
            $querySettings->setRespectStoragePage(false);
            $transactionRepository->setDefaultQuerySettings($querySettings);
            $transaction = $transactionRepository->findOneByTxnId($hash);

            if (!$transaction) {
                $data = [
                    'status' => 'not found'
                ];

                $response = $this->responseFactory->createResponse()
                    ->withHeader('Content-Type', 'application/json; charset=utf-8')
                    ->withStatus(404, 'Not Found');
                $response->getBody()->write(json_encode($data));
                return $response;
            }

            $payment = $transaction->getPayment();

            $orderItem = $this->getOrderItem($payment);
            $cart = $this->getCart($orderItem);

            $settings = [];
            if ($status === 'confirmed') {
                $payment->setStatus('paid');

                $paymentRepository = GeneralUtility::makeInstance(PaymentRepository::class);
                $paymentRepository->update($payment);

                $persistenceManager = GeneralUtility::makeInstance(PersistenceManagerInterface::class);
                $persistenceManager->persistAll();

                $eventDispatcher = GeneralUtility::makeInstance(EventDispatcherInterface::class);
                $finishEvent = new FinishEvent($cart->getCart(), $orderItem, $settings);
                $eventDispatcher->dispatch($finishEvent);
            }
            if ($status === 'canceled') {
                $payment->setStatus('canceled');
                // ToDo: Dispatch Event
            }

            $data = [
                'status' => 'ok',
            ];

            $response = $this->responseFactory->createResponse()
                ->withHeader('Content-Type', 'application/json; charset=utf-8');
            $response->getBody()->write(json_encode($data));
            return $response;
        }
        return $handler->handle($request);
    }

    protected function getOrderItem(Payment $payment)
    {
        $orderItemRepository = GeneralUtility::makeInstance(ItemRepository::class);
        $querySettings = $orderItemRepository->createQuery()->getQuerySettings();
        $querySettings->setRespectStoragePage(false);
        $orderItemRepository->setDefaultQuerySettings($querySettings);
        $orderItem = $orderItemRepository->findOneByPayment($payment);

        return $orderItem;
    }

    protected function getCart(Item $orderItem)
    {
        $cartRepository = GeneralUtility::makeInstance(CartRepository::class);
        $querySettings = $cartRepository->createQuery()->getQuerySettings();
        $querySettings->setRespectStoragePage(false);
        $cartRepository->setDefaultQuerySettings($querySettings);
        $cart = $cartRepository->findOneByOrderItem($orderItem);

        return $cart;
    }
}

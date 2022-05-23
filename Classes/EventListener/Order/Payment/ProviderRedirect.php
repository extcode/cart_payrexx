<?php
declare(strict_types=1);
namespace Extcode\CartPayrexx\EventListener\Order\Payment;

/*
 * This file is part of the package extcode/cart-payrexx.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Model\Cart;
use Extcode\Cart\Domain\Model\Order\Item as OrderItem;
use Extcode\Cart\Domain\Model\Order\Payment;
use Extcode\Cart\Domain\Model\Order\Transaction;
use Extcode\Cart\Domain\Repository\CartRepository;
use Extcode\Cart\Domain\Repository\Order\PaymentRepository;
use Extcode\Cart\Domain\Repository\Order\TransactionRepository;
use Extcode\Cart\Event\Order\PaymentEvent;
use Payrexx\Models\Request\Gateway;
use Payrexx\Payrexx;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

class ProviderRedirect
{
    /**
     * @var OrderItem
     */
    protected $orderItem;

    /**
     * @var PersistenceManager
     */
    protected $persistenceManager;

    /**
     * @var ConfigurationManager
     */
    protected $configurationManager;

    /**
     * @var TypoScriptService
     */
    protected $typoScriptService;

    /**
     * @var UriBuilder
     */
    protected $uriBuilder;

    /**
     * @var CartRepository
     */
    protected $cartRepository;

    /**
     * @var PaymentRepository
     */
    protected $paymentRepository;

    /**
     * @var array
     */
    protected $conf = [];

    /**
     * @var array
     */
    protected $cartConf = [];

    /**
     * @var array
     */
    protected $paymentQuery = [];

    public function __construct(
        ConfigurationManager $configurationManager,
        PersistenceManager $persistenceManager,
        TypoScriptService $typoScriptService,
        UriBuilder $uriBuilder,
        CartRepository $cartRepository,
        PaymentRepository $paymentRepository
    ) {
        $this->configurationManager = $configurationManager;
        $this->persistenceManager = $persistenceManager;
        $this->typoScriptService = $typoScriptService;
        $this->uriBuilder = $uriBuilder;
        $this->cartRepository = $cartRepository;
        $this->paymentRepository = $paymentRepository;

        $this->conf = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_FRAMEWORK,
            'CartPayrexx'
        );

        $this->cartConf = $this->configurationManager->getConfiguration(
            ConfigurationManager::CONFIGURATION_TYPE_FRAMEWORK,
            'Cart'
        );
    }

    public function __invoke(PaymentEvent $event): void
    {
        $this->orderItem = $event->getOrderItem();

        $payment = $this->orderItem->getPayment();
        $provider = $payment->getProvider();

        if ($provider !== 'PAYREXX') {
            return;
        }

        $instanceName = $this->conf['instanceName'];
        $secret = $this->conf['secret'];

        if (empty($instanceName) || empty($secret)) {
            // ToDo: restore cart and print an error
            return;
        }

        $cart = $this->persistCartToDatabase($event);

        $payrexx = new Payrexx($instanceName, $secret);
        $gateway = new Gateway();
        $gateway->setAmount($this->orderItem->getTotalGross() * 100);
        $gateway->setCurrency($this->orderItem->getCurrencyCode());

        $gateway->setSuccessRedirectUrl($this->buildReturnUrl('success', $cart->getSHash()));
        $gateway->setFailedRedirectUrl($this->buildReturnUrl('failed', $cart->getFHash()));
        $gateway->setCancelRedirectUrl($this->buildReturnUrl('cancel', $cart->getFHash()));

        $gateway->setPsp([]);
        $gateway->setPreAuthorization(false);
        $gateway->setReservation(false);
        $gateway->setReferenceId($this->orderItem->getOrderNumber());

        try {
            $response = $payrexx->create($gateway);

            $this->saveTransaction($payment, $response);

            header('location:' . $response->getLink());
        } catch (\Payrexx\PayrexxException $e) {
            // ToDo: restore cart and print an error
            print $e->getMessage();
        }

        $event->setPropagationStopped(true);
    }

    /**
     * Builds a return URL to Cart order controller action
     */
    protected function buildReturnUrl(string $action, string $hash): string
    {
        $pid = (int)$this->cartConf['settings']['cart']['pid'];

        $arguments = [
            'tx_cartpayrexx_cart' => [
                'controller' => 'Order\Payment',
                'order' => $this->orderItem->getUid(),
                'action' => $action,
                'hash' => $hash
            ]
        ];

        $uriBuilder = $this->uriBuilder;

        return $uriBuilder->reset()
            ->setTargetPageUid($pid)
            ->setTargetPageType((int)$this->conf['redirectTypeNum'])
            ->setCreateAbsoluteUri(true)
            ->setArguments($arguments)
            ->build();
    }

    /**
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
     */
    protected function persistCartToDatabase(PaymentEvent $event): Cart
    {
        $newCart = new Cart();
        $newCart->setOrderItem($this->orderItem);
        $newCart->setCart($event->getCart());
        $newCart->setPid((int)$this->cartConf['settings']['order']['pid']);

        $this->cartRepository->add($newCart);
        $this->persistenceManager->persistAll();

        return $newCart;
    }

    protected function saveTransaction(Payment $payment, $response): void
    {
        $transaction = GeneralUtility::makeInstance(Transaction::class);
        $transaction->setPid($payment->getPid());
        $transaction->setTxnId($response->getHash());
        $transactionNote = [
            'createdAt' => $response->getCreatedAt(),
            'id' => $response->getId(),
        ];
        $transaction->setNote(json_encode($transactionNote));

        $transactionRepository = GeneralUtility::makeInstance(TransactionRepository::class);
        $transactionRepository->add($transaction);

        $payment->addTransaction($transaction);
        $paymentRepository = GeneralUtility::makeInstance(PaymentRepository::class);
        $paymentRepository->update($payment);

        $this->persistenceManager->persistAll();
    }
}

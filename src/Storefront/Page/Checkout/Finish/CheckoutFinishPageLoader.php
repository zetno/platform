<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Finish;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Exception\OrderNotFoundException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Checkout\Order\SalesChannel\AbstractOrderRoute;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Uuid\Exception\InvalidUuidException;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @package storefront
 */
class CheckoutFinishPageLoader
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var GenericPageLoaderInterface
     */
    private $genericLoader;

    /**
     * @var AbstractOrderRoute
     */
    private $orderRoute;

    /**
     * @internal
     */
    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        GenericPageLoaderInterface $genericLoader,
        AbstractOrderRoute $orderRoute
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->genericLoader = $genericLoader;
        $this->orderRoute = $orderRoute;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     * @throws OrderNotFoundException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): CheckoutFinishPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = CheckoutFinishPage::createFrom($page);

        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        Profiler::trace('finish-page-order-loading', function () use ($page, $request, $salesChannelContext): void {
            $page->setOrder($this->getOrder($request, $salesChannelContext));
        });

        $page->setChangedPayment((bool) $request->get('changedPayment', false));

        $page->setPaymentFailed((bool) $request->get('paymentFailed', false));

        $this->eventDispatcher->dispatch(
            new CheckoutFinishPageLoadedEvent($page, $salesChannelContext, $request)
        );

        if ($page->getOrder()->getItemRounding()) {
            $salesChannelContext->setItemRounding($page->getOrder()->getItemRounding());
            $salesChannelContext->getContext()->setRounding($page->getOrder()->getItemRounding());
        }
        if ($page->getOrder()->getTotalRounding()) {
            $salesChannelContext->setTotalRounding($page->getOrder()->getTotalRounding());
        }

        return $page;
    }

    /**
     * @throws CustomerNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     * @throws OrderNotFoundException
     */
    private function getOrder(Request $request, SalesChannelContext $salesChannelContext): OrderEntity
    {
        $customer = $salesChannelContext->getCustomer();
        if ($customer === null) {
            throw CartException::customerNotLoggedIn();
        }

        $orderId = $request->get('orderId');
        if (!$orderId) {
            throw new MissingRequestParameterException('orderId', '/orderId');
        }

        $criteria = (new Criteria([$orderId]))
            ->addFilter(new EqualsFilter('order.orderCustomer.customerId', $customer->getId()))
            ->addAssociation('lineItems.cover')
            ->addAssociation('transactions.paymentMethod')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('billingAddress.salutation')
            ->addAssociation('billingAddress.country')
            ->addAssociation('billingAddress.countryState')
            ->addAssociation('deliveries.shippingOrderAddress.salutation')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('deliveries.shippingOrderAddress.countryState');

        $criteria->getAssociation('transactions')->addSorting(new FieldSorting('createdAt'));

        $this->eventDispatcher->dispatch(
            new CheckoutFinishPageOrderCriteriaEvent($criteria, $salesChannelContext)
        );

        try {
            $searchResult = $this->orderRoute
                ->load(new Request(), $salesChannelContext, $criteria)
                ->getOrders();
        } catch (InvalidUuidException $e) {
            if (Feature::isActive('v6.5.0.0')) {
                throw OrderException::orderNotFound($orderId);
            }

            throw new OrderNotFoundException($orderId);
        }

        /** @var OrderEntity|null $order */
        $order = $searchResult->get($orderId);

        if (!$order) {
            if (Feature::isActive('v6.5.0.0')) {
                throw OrderException::orderNotFound($orderId);
            }

            throw new OrderNotFoundException($orderId);
        }

        return $order;
    }
}

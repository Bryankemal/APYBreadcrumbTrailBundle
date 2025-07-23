<?php

/*
 * This file is part of the APYBreadcrumbTrailBundle.
 *
 * (c) Abhoryo <abhoryo@free.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace APY\BreadcrumbTrailBundle\EventListener;

use APY\BreadcrumbTrailBundle\Annotation\Breadcrumb;
use APY\BreadcrumbTrailBundle\Annotation\ResetBreadcrumbTrail;
use APY\BreadcrumbTrailBundle\BreadcrumbTrail\Trail;
use APY\BreadcrumbTrailBundle\MixedAnnotationWithAttributeBreadcrumbsException;
use Symfony\Component\HttpKernel\Event\KernelEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class BreadcrumbListener
{
    /**
     * @var Reader An Reader instance
     */
    protected $reader;

    /**
     * @var Trail An Trail instance
     */
    protected $breadcrumbTrail;

    private $supportedAttributes = [
        Breadcrumb::class,
        ResetBreadcrumbTrail::class,
    ];

    /**
     * Constructor.
     *
     * @param Reader $reader          An Reader instance
     * @param Trail  $breadcrumbTrail An Trail instance
     */
    public function __construct(Reader $reader, Trail $breadcrumbTrail)
    {
        $this->reader = $reader;
        $this->breadcrumbTrail = $breadcrumbTrail;
    }

    /**
     * @param \Symfony\Component\HttpKernel\Event\FilterControllerEvent|\Symfony\Component\HttpKernel\Event\ControllerEvent $event
     */
    public function onKernelController(KernelEvent $event)
    {
        $controller = $event->getController();

        $reflectableClass = \is_array($controller) ? $controller[0] : \get_class($controller);
        $reflectableMethod = \is_array($controller) ? $controller[1] : '__invoke';

        // Annotations from class
        $class = new \ReflectionClass($reflectableClass);

        // Manage JMSSecurityExtraBundle proxy class
        if (false !== $className = $this->getRealClass($class->getName())) {
            $class = new \ReflectionClass($className);
        }

        if ($class->isAbstract()) {
            throw new \InvalidArgumentException(\sprintf('Annotations from class "%s" cannot be read as it is abstract.', $class));
        }

        $kernelRequest = \defined(HttpKernelInterface::class.'::MASTER_REQUEST') ? HttpKernelInterface::MASTER_REQUEST : HttpKernelInterface::MAIN_REQUEST;

        if ($kernelRequest == $event->getRequestType()) {
            $this->breadcrumbTrail->reset();

            if ($this->supportsLoadingAttributes()) {
                $classAttributeBreadcrumbs = $this->getAttributes($class);
                if (\count($classAttributeBreadcrumbs) > 0) {
                    $classBreadcrumbs = $classAttributeBreadcrumbs;
                }
            }
            $this->addBreadcrumbsToTrail($classBreadcrumbs);
        }
    }

    /**
     * @param array $annotations Array of Breadcrumb annotations
     */
    private function addBreadcrumbsToTrail(array $annotations)
    {
        // requirements (@Breadcrumb)
        foreach ($annotations as $annotation) {
            if ($annotation instanceof ResetBreadcrumbTrail) {
                $this->breadcrumbTrail->reset();

                continue;
            }

            if ($annotation instanceof Breadcrumb) {
                $template = $annotation->getTemplate();
                $title = $annotation->getTitle();

                if (null === $title) {
                    trigger_deprecation('apy/breadcrumb-bundle', '1.8', 'Resetting the breadcrumb trail by passing a Breadcrumb without parameters, and will throw an exception in v2.0. Use #[ResetBreadcrumbTrail] attribute instead.');
                }

                if (null != $template) {
                    $this->breadcrumbTrail->setTemplate($template);
                    if (null === $title) {
                        continue;
                    }
                }

                $this->breadcrumbTrail->add(
                    $title,
                    $annotation->getRouteName(),
                    $annotation->getRouteParameters(),
                    $annotation->getRouteAbsolute(),
                    $annotation->getPosition(),
                    $annotation->getAttributes()
                );
            }
        }
    }

    private function getRealClass($className)
    {
        if (false === $pos = strrpos($className, '\\__CG__\\')) {
            return false;
        }

        return substr($className, $pos + 8);
    }

    private function supportsLoadingAttributes(): bool
    {
        return \PHP_VERSION_ID >= 80000;
    }

    /**
     * @param \ReflectionClass|\ReflectionMethod $reflected
     *
     * @return array<Breadcrumb>
     */
    private function getAttributes($reflected): array
    {
        if (false === $this->supportsLoadingAttributes()) {
            throw new \RuntimeException('Detected an attempt on getting attributes while your version of PHP does not support this.');
        }

        $attributes = [];
        foreach ($reflected->getAttributes() as $reflectionAttribute) {
            if (false === \in_array($reflectionAttribute->getName(), $this->supportedAttributes)) {
                continue;
            }

            $attributes[] = $reflectionAttribute->newInstance();
        }

        return $attributes;
    }
}

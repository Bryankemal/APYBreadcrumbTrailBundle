<?php

/*
 * This file is part of the APYBreadcrumbTrailBundle.
 *
 * (c) Abhoryo <abhoryo@free.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

// Remplace src/Resources/config/services.xml : Symfony 8 a supprimé
// Symfony\Component\DependencyInjection\Loader\XmlFileLoader.
// Adapter aussi APYBreadcrumbTrailExtension::load() :
//   - use ...\Loader\PhpFileLoader;  (au lieu de XmlFileLoader)
//   - new PhpFileLoader(...)         (au lieu de new XmlFileLoader(...))
//   - $loader->load('services.php'); (au lieu de 'services.xml')

use APY\BreadcrumbTrailBundle\BreadcrumbTrail\Trail;
use APY\BreadcrumbTrailBundle\EventListener\BreadcrumbListener;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->private()
        ->autoconfigure()
        ->autowire();

    $services->load('APY\\BreadcrumbTrailBundle\\', '../../*')
        ->exclude('../../{Annotation,Resources}');

    $services->set(Trail::class)
        ->call('setTemplate', ['%apy_breadcrumb_trail.template%']);

    $services->alias('apy_breadcrumb_trail', Trail::class)
        ->public();

    $services->set(BreadcrumbListener::class)
        ->tag('kernel.event_listener', [
            'event' => 'kernel.controller',
            'method' => 'onKernelController',
            'priority' => -1,
        ]);

    $services->alias('apy_breadcrumb_trail.annotation.listener', BreadcrumbListener::class)
        ->public();
};

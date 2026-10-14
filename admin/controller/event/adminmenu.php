<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

namespace Opencart\Admin\Controller\Extension\Alliance\Event;

use Opencart\System\Engine\Controller;

/**
 * Class Adminmenu.
 */
class Adminmenu extends Controller
{
    /**
     * @param mixed ...$args by-ref: (&$route, &$data[, &$code])
     * @return void
     */
    public function addMenuLink(&...$args): void
    {
        if (count($args) < 2
            || !is_array($args[1])
            || !isset($args[1]['menus'])
            || !is_array($args[1]['menus'])
        ) {
            return;
        }

        $this->load->language('extension/alliance/alliance/subscriptionplan');

        $userToken = $this->session->data['user_token'] ?? '';

        $args[1]['menus'][] = [
            'id' => 'menu-alliance-subscriptions',
            'icon' => 'fa-solid fa-rotate',
            'name' => $this->language->get('heading_title'),
            'href' => '',
            'children' => [
                [
                    'name' => $this->language->get('heading_title'),
                    'href' => $this->url->link(
                        'extension/alliance/alliance/subscriptionplan',
                        'user_token=' . $userToken
                    ),
                ],
                [
                    'name' => $this->language->get('text_subscriptions_menu'),
                    'href' => $this->url->link(
                        'extension/alliance/alliance/subscription',
                        'user_token=' . $userToken
                    ),
                ],
            ],
        ];
    }
}

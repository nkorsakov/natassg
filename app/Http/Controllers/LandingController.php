<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class LandingController extends Controller
{
    public function __invoke(): Response
    {
        $title = 'SkyDesk — рабочее пространство личного помощника';
        $description = 'Поручения, календарь, деньги на руках и отчёты — всё в одном окне. 30 дней бесплатно, далее от 500 ₽/мес.';
        $url = rtrim((string) config('app.url'), '/').'/';
        $image = $url.'images/landing/hero-main.jpg';

        return Inertia::render('Landing/Index')
            ->withViewData([
                'seo' => [
                    'title' => $title,
                    'description' => $description,
                    'url' => $url,
                    'image' => $image,
                    'json_ld' => [
                        '@context' => 'https://schema.org',
                        '@type' => 'SoftwareApplication',
                        'name' => 'SkyDesk',
                        'applicationCategory' => 'BusinessApplication',
                        'operatingSystem' => 'Web, PWA',
                        'description' => $description,
                        'inLanguage' => 'ru',
                        'url' => $url,
                        'offers' => [
                            '@type' => 'AggregateOffer',
                            'lowPrice' => '500',
                            'highPrice' => '5000',
                            'priceCurrency' => 'RUB',
                            'offerCount' => 2,
                            'offers' => [
                                [
                                    '@type' => 'Offer',
                                    'name' => 'Месяц',
                                    'price' => '500',
                                    'priceCurrency' => 'RUB',
                                    'description' => '30 дней бесплатно, далее 500 ₽ в месяц',
                                ],
                                [
                                    '@type' => 'Offer',
                                    'name' => 'Год',
                                    'price' => '5000',
                                    'priceCurrency' => 'RUB',
                                    'description' => '30 дней бесплатно, далее 5 000 ₽ в год (−17%)',
                                ],
                            ],
                        ],
                    ],
                ],
            ]);
    }
}

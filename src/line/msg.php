<?php

require_once "./vendor/autoload.php";

require_once __DIR__.'/../io/rss.php';
require_once __DIR__.'/../util/log.php';
require_once __DIR__.'/../util/util.php';

use LINE\LINEBot\MessageBuilder\FlexMessageBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\BoxComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ButtonComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\IconComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\ImageComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ComponentBuilder\TextComponentBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\CarouselContainerBuilder;
use LINE\LINEBot\MessageBuilder\Flex\ContainerBuilder\BubbleContainerBuilder;

use LINE\LINEBot\TemplateActionBuilder\UriTemplateActionBuilder;
use LINE\LINEBot\TemplateActionBuilder\PostbackTemplateActionBuilder;

use LINE\LINEBot\Constant\Flex\ComponentIconSize;
use LINE\LINEBot\Constant\Flex\ComponentImageSize;
use LINE\LINEBot\Constant\Flex\ComponentImageAspectRatio;
use LINE\LINEBot\Constant\Flex\ComponentImageAspectMode;
use LINE\LINEBot\Constant\Flex\ComponentFontSize;
use LINE\LINEBot\Constant\Flex\ComponentFontWeight;
use LINE\LINEBot\Constant\Flex\ComponentMargin;
use LINE\LINEBot\Constant\Flex\ComponentSpacing;
use LINE\LINEBot\Constant\Flex\ComponentButtonStyle;
use LINE\LINEBot\Constant\Flex\ComponentButtonHeight;
use LINE\LINEBot\Constant\Flex\ComponentSpaceSize;
use LINE\LINEBot\Constant\Flex\ComponentGravity;
use LINE\LINEBot\QuickReplyBuilder\ButtonBuilder\QuickReplyButtonBuilder;
use LINE\LINEBot\QuickReplyBuilder\QuickReplyMessageBuilder;
use LINE\LINEBot\TemplateActionBuilder\MessageTemplateActionBuilder;
use LINE\LINEBot\Constant\Flex\ComponentLayout;

// rssToFlexMessage -> replyFlexMessageFromRss
// $rssから$rTokenを利用してreplyを送信。
// @param $bot
// @param string $rToken
// @param array $rss
function replyFlexMessageFromRss($bot, $rToken, $rss)
{
    $bubbles = [];
    foreach($rss as $r){
        $title = $r['title'];
        if(mb_strlen($title) > 40) $title = mb_substr($title, 0, 39);
        $bubbles[] = BubbleContainerBuilder::builder()
                   ->setBody(
                       new BoxComponentBuilder(ComponentLayout::VERTICAL,[
                           new TextComponentBuilder($title),
                           (new ButtonComponentBuilder($r['link']))
                           ->setAction(new UriTemplateActionBuilder('Go', $r['link'])),
                       ])
                   );
    }
    return $bot->replyMessage(
        $rToken,
        FlexMessageBuilder::builder()
        ->setAltText('articles')
        ->setContents(
            CarouselContainerBuilder::builder()
            ->setContents($bubbles)
        )
    );
}
// sendCategoriesMessage -> replyCategoriesMessage
// @param $bot
// @param string $rToken
function replyCategoriesMessage($bot, $rToken)
{
    $titles = listRssCategories($_ENV['RSS_CONFIG_PATH']);

    $qreplyButtons = [];
    foreach($titles as $t){
        $qreplyButtons[] = (
            new QuickReplyButtonBuilder(new PostbackTemplateActionBuilder($t, "reply=category&category=${t}", $t))
        );
    }
    $qreply = new QuickReplyMessageBuilder($qreplyButtons);
    return $bot->replyMessage(
        $rToken,
        FlexMessageBuilder::builder()
        ->setContents(
            BubbleContainerBuilder::builder()
            ->setBody(new BoxComponentBuilder(ComponentLayout::VERTICAL, [new TextComponentBuilder('カテゴリーを選択してください。')]))
        )
        ->setAltText('articles')
        ->setQuickReply($qreply)
    );
}

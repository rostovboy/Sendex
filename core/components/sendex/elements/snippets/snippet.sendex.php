<?php
/** @var array $scriptProperties */
/** @var Sendex $Sendex */
$Sendex = $modx->getService('sendex', 'Sendex', $modx->getOption('sendex_core_path', null, $modx->getOption('core_path') . 'components/sendex/') . 'model/sendex/', $scriptProperties);
if (!($Sendex instanceof Sendex)) return '';

if (empty($tplSubscribeAuth)) {
    $tplSubscribeAuth = 'tpl.Sendex.subscribe.auth';
}
if (empty($tplSubscribeGuest)) {
    $tplSubscribeGuest = 'tpl.Sendex.subscribe.guest';
}
if (empty($tplUnsubscribe)) {
    $tplUnsubscribe = 'tpl.Sendex.unsubscribe';
}
if (empty($tplActivate)) {
    $tplActivate = 'tpl.Sendex.activate';
}
if (empty($linkTTL)) {
    $linkTTL = 1800;
}

if (empty($id) || !$newsletter = $modx->getObject('sxNewsletter', $id)) {
    return $modx->lexicon('sendex_newsletter_err_ns');
}

/** @var sxNewsletter $newsletter */
if (!$newsletter->active && empty($showInactive)) {
    return $modx->lexicon('sendex_newsletter_err_disabled');
}

$placeholders = $newsletter->toArray();
$placeholders['message'] = '';
$placeholders['error'] = 0;
if ($modx->user->isAuthenticated($modx->context->key)) {
    $placeholders = array_merge(
        $modx->user->toArray(),
        $modx->user->Profile->toArray(),
        $placeholders
    );
}

$isAuthenticated = $modx->user->isAuthenticated($modx->context->key);

if (!empty($_REQUEST['sx_action'])) {
    $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';

    $params = $_GET;
    unset($params[$modx->getOption('request_param_alias')]);
    unset($params[$modx->getOption('request_param_id')]);

    switch ($_REQUEST['sx_action']) {
        case 'subscribe':
            if ($isAuthenticated && $modx->user->id) {
                if (!$response = $newsletter->Subscribe($modx->user->id)) {
                    $placeholders['message'] = $modx->lexicon('sendex_subscribe_err_email_wrong');
                    $placeholders['error'] = 1;
                }
            } elseif (!empty($_REQUEST['email'])) {
                $email = htmlentities(strip_tags(urldecode($_REQUEST['email'])));
                $response = $newsletter->checkEmail($email, $modx->user->id, $linkTTL);
                if ($response === true) {
                    $placeholders['message'] = $modx->lexicon('sendex_subscribe_err_already');
                } elseif ($response === false) {
                    $placeholders['message'] = $modx->lexicon('sendex_subscribe_err_email_wrong');
                    $placeholders['error'] = 1;
                } else {
                    $params['hash'] = $response;
                    $params['sx_action'] = 'confirm';
                    $placeholders['link'] = $modx->makeUrl($modx->resource->id, $modx->context->key, $params, 'full');
                    $placeholders['email_body'] = $modx->getChunk($tplActivate, $placeholders);
                    $response = $Sendex->sendEmail($email, $placeholders);
                    if ($response !== true) {
                        $placeholders['message'] = $modx->lexicon('sendex_subscribe_err_email_send');
                        $placeholders['error'] = 1;
                    } else {
                        $params['sx_subscribed'] = 1;
                    }
                }
            } else {
                $placeholders['message'] = $modx->lexicon('sendex_subscribe_err_email_ns');
                $placeholders['error'] = 1;
            }
            unset($params['email'], $params['hash']);
            break;
        case 'confirm':
            if (!empty($_REQUEST['hash'])) {
                $response = $newsletter->confirmEmail($_REQUEST['hash']);
                $params['sx_confirmed'] = 1;
                unset($params['hash']);
            }
            break;
        case 'unsubscribe':
            if (!empty($_REQUEST['code'])) {
                $response = $newsletter->unSubscribe($_REQUEST['code']);
                $params['sx_unsubscribed'] = 1;
            }
            unset($params['code']);
            break;
    }

    unset($params['sx_action']);
    if (!$isAjax && empty($placeholders['message'])) {
        $modx->sendRedirect($modx->makeUrl($modx->resource->id, $modx->context->key, $params, 'full'));
    }
}

if ($isAuthenticated && $id = $newsletter->isSubscribed($modx->user->id)) {
    if ($subscriber = $modx->getObject('sxSubscriber', $id)) {
        $placeholders = array_merge($subscriber->toArray(), $placeholders);
    }
    $output = $modx->getChunk($tplUnsubscribe, $placeholders);
} else {
    $output = $isAuthenticated
        ? $modx->getChunk($tplSubscribeAuth, $placeholders)
        : $modx->getChunk($tplSubscribeGuest, $placeholders);
}

if (!empty($isAjax)) {
    @session_write_close();
    exit($output);
} else {
    return $output;
}

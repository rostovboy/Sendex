<?php

/**
 * Get an Newsletter
 */
class sxNewsletterGetProcessor extends modObjectGetProcessor
{
    public $objectType = 'sxNewsletter';
    public $classKey = 'sxNewsletter';
    public $languageTopics = array('sendex:default');
}

return 'sxNewsletterGetProcessor';
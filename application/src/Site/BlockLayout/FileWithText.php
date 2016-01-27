<?php
namespace Omeka\Site\BlockLayout;

use Zend\Form\Element\Select;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Entity\SitePageBlock;
use Omeka\Stdlib\ErrorStore;
use Zend\Form\Element\Textarea;
use Zend\View\Renderer\PhpRenderer;

class FileWithText extends AbstractBlockLayout
{
    public function getLabel()
    {
        $translator = $this->getServiceLocator()->get('MvcTranslator');
        return $translator->translate('File with Text');
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore)
    {
        $htmlPurifier = $this->getServiceLocator()->get('Omeka\htmlPurifier');
        $data = $block->getData();
        $data['text'] = $htmlPurifier->purify($this->getData($data, 'text'));
        $block->setData($data);
    }

    public function form(PhpRenderer $view, SiteRepresentation $site,
        SitePageBlockRepresentation $block = null
    ) {
        $textarea = new Textarea("o:block[__blockIndex__][o:data][html]");
        $textarea->setAttribute('class', 'block-html full wysiwyg');
        if ($block) {
            $textarea->setAttribute('value', $this->getData($block->data(), 'html'));
        }

        return $this->thumbnailTypeSelect($view, $site, $block)
            . $this->alignmentClassSelect($view, $site, $block)
            . $this->attachmentsForm($view, $site, $block)
            . $view->formRow($textarea);

    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $attachments = $block->attachments();
        if (!$attachments) {
            return '';
        }

        $alignmentClass = $this->getData($block->data(), 'alignment', 'left');
        $thumbnailType = $this->getData($block->data(), 'thumbnail_type', 'square');
        $html = $this->getData($block->data(), 'html');

        return $view->partial('common/block-layout/file-with-text', array(
            'block' => $block,
            'attachments' => $attachments,
            'alignmentClass' => $alignmentClass,
            'thumbnailType' => $thumbnailType,
            'html' => $html
        ));

    }

    public function alignmentClassSelect(PhpRenderer $view, SiteRepresentation $site,
        SitePageBlockRepresentation $block = null
    ) {
        $alignments = array('left', 'right');
        $data = $block ? $block->data() : [];
        $alignment = $this->getData($data, 'alignment', 'left');
        $select = new Select('o:block[__blockIndex__][o:data][alignment]');
        $select->setValueOptions(array_combine($alignments, $alignments))->setValue($alignment);
        return '<label class="thumbnail-option">Thumbnail Alignment ' . $view->formSelect($select) . '</label>';
    }
}

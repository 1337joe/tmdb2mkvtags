<?php
/**
 * Write a Matroska tags XML file
 *
 * PHP version 7
 *
 * @author  Christian Weiske <cweiske@cweiske.de>
 * @license https://www.gnu.org/licenses/gpl-3.0.txt GPL-3.0-or-later
 * @link    https://www.matroska.org/technical/tagging.html
 * @link    https://developers.themoviedb.org/3/
 */

 class MkvTagXMLWriter extends XMLWriter
{
    public function actor($actorName, $characterName)
    {
        $this->startElement('Simple');
        $this->startElement('Name');
        $this->text('ACTOR');
        $this->endElement();
        $this->startElement('String');
        $this->text($actorName);
        $this->endElement();
        $this->simple('CHARACTER', $characterName);
        $this->endElement();//Simple
    }

    public function simple($key, $value, $language = null)
    {
        $this->startElement('Simple');
        $this->startElement('Name');
        $this->text($key);
        $this->endElement();
        $this->startElement('String');
        $this->text($value);
        $this->endElement();
        if ($language) {
            $this->startElement('TagLanguage');
            $this->text($language);
            $this->endElement();
        }
        $this->endElement();
    }

    public function targetType($value)
    {
        $this->startElement('Targets');
        $this->startElement('TargetType');
        $this->text($value);
        $this->endElement();
        $this->endElement();
    }
}
?>
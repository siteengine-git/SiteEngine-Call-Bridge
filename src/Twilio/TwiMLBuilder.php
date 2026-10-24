<?php
/**
 * Minimal TwiML builder with no external PHP extension dependency.
 */

declare(strict_types=1);
namespace SiteEngine\CallBridge\Twilio;
defined('ABSPATH') || exit;
final class TwiMLBuilder
{
    private array $parts=[];
    private bool $dialOpen=false;
    public function say(string $text,string $voice='Polly.Joanna',string $language='en-US'): self
    {
        $this->parts[]='<Say voice="'.$this->x($voice).'" language="'.$this->x($language).'">'.$this->x($text).'</Say>'; return $this;
    }
    public function openDial(array $attrs=[]): self
    {
        if($this->dialOpen) throw new \LogicException('Dial already open.');
        $buf='<Dial'; foreach($attrs as $k=>$v){if($v===null||$v==='')continue;$buf.=' '.$this->attrName((string)$k).'="'.$this->x((string)$v).'"';}$buf.='>';$this->parts[]=$buf;$this->dialOpen=true;return $this;
    }
    public function number(string $e164,?string $whisperUrl=null): self
    {
        if(!$this->dialOpen) throw new \LogicException('Number requires open Dial.');
        $attrs=''; if($whisperUrl!==null&&$whisperUrl!=='')$attrs=' url="'.$this->x($whisperUrl).'" method="GET"';
        $this->parts[]='<Number'.$attrs.'>'.$this->x($e164).'</Number>'; return $this;
    }
    public function closeDial(): self { if($this->dialOpen){$this->parts[]='</Dial>';$this->dialOpen=false;} return $this; }
    public function hangup(): self { $this->parts[]='<Hangup/>'; return $this; }
    public function render(): string { $this->closeDial(); return '<?xml version="1.0" encoding="UTF-8"?><Response>'.implode('',$this->parts).'</Response>'; }
    private function x(string $value): string { return htmlspecialchars($value,ENT_QUOTES|ENT_XML1,'UTF-8'); }
    private function attrName(string $name): string { return preg_match('/^[A-Za-z][A-Za-z0-9]*$/',$name)===1?$name:throw new \InvalidArgumentException('Invalid TwiML attribute name.'); }
}

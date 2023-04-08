<?php

class __Mustache_cc906f354474ba52a07c57caf23fc065 extends Mustache_Template
{
    private $lambdaHelper;

    public function renderInternal(Mustache_Context $context, $indent = '')
    {
        $this->lambdaHelper = new Mustache_LambdaHelper($this->mustache, $context);
        $buffer = '';

        $buffer .= $indent . '<div';
        $buffer .= ' class="calendarwrapper"';
        // 'courseid' section
        $value = $context->find('courseid');
        $buffer .= $this->section4f3e547ac50707b30ffc2cec5e028045($context, $indent, $value);
        // 'categoryid' section
        $value = $context->find('categoryid');
        $buffer .= $this->sectionA00eaaaf78214a10bae0a4f10432dae0($context, $indent, $value);
        $buffer .= ' data-context-id="';
        $value = $this->resolveValue($context->find('defaulteventcontext'), $context);
        $buffer .= call_user_func($this->mustache->getEscape(), $value);
        $buffer .= '"';
        $buffer .= ' data-month="';
        $value = $this->resolveValue($context->findDot('date.mon'), $context);
        $buffer .= call_user_func($this->mustache->getEscape(), $value);
        $buffer .= '"';
        $buffer .= ' data-year="';
        $value = $this->resolveValue($context->findDot('date.year'), $context);
        $buffer .= call_user_func($this->mustache->getEscape(), $value);
        $buffer .= '"';
        $buffer .= ' data-view="month"';
        $buffer .= '>
';
        if ($partial = $this->mustache->loadPartial('core_calendar/month_navigation')) {
            $buffer .= $partial->renderInternal($context, $indent . '    ');
        }
        if ($partial = $this->mustache->loadPartial('core/overlay_loading')) {
            $buffer .= $partial->renderInternal($context, $indent . '    ');
        }
        $buffer .= $indent . '    <table id="month-detailed-';
        $value = $this->resolveValue($context->find('uniqid'), $context);
        $buffer .= call_user_func($this->mustache->getEscape(), $value);
        $buffer .= '" class="calendarmonth calendartable m-b-0">
';
        $buffer .= $indent . '        <thead>
';
        $buffer .= $indent . '        <tr>
';
        // 'daynames' section
        $value = $context->find('daynames');
        $buffer .= $this->section460ad67446eb65d170170a3c765240b5($context, $indent, $value);
        $buffer .= $indent . '        </tr>
';
        $buffer .= $indent . '        </thead>
';
        $buffer .= $indent . '        <tbody>
';
        // 'weeks' section
        $value = $context->find('weeks');
        $buffer .= $this->sectionA07fd3399f7eec91546450dc074994d7($context, $indent, $value);
        $buffer .= $indent . '        </tbody>
';
        $buffer .= $indent . '    </table>
';
        $buffer .= $indent . '</div>
';
        // 'js' section
        $value = $context->find('js');
        $buffer .= $this->section453db4c1e03f6f1d21b1aa1f4ea3d3c4($context, $indent, $value);

        return $buffer;
    }

    private function section4f3e547ac50707b30ffc2cec5e028045(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = ' data-courseid="{{courseid}}"';
            $result = call_user_func($value, $source, $this->lambdaHelper);
            if (strpos($result, '{{') === false) {
                $buffer .= $result;
            } else {
                $buffer .= $this->mustache
                    ->loadLambda((string) $result)
                    ->renderInternal($context);
            }
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= ' data-courseid="';
                $value = $this->resolveValue($context->find('courseid'), $context);
                $buffer .= call_user_func($this->mustache->getEscape(), $value);
                $buffer .= '"';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function sectionA00eaaaf78214a10bae0a4f10432dae0(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = ' data-categoryid="{{categoryid}}"';
            $result = call_user_func($value, $source, $this->lambdaHelper);
            if (strpos($result, '{{') === false) {
                $buffer .= $result;
            } else {
                $buffer .= $this->mustache
                    ->loadLambda((string) $result)
                    ->renderInternal($context);
            }
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= ' data-categoryid="';
                $value = $this->resolveValue($context->find('categoryid'), $context);
                $buffer .= call_user_func($this->mustache->getEscape(), $value);
                $buffer .= '"';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function section460ad67446eb65d170170a3c765240b5(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = '
                <th class="header text-xs-center">
                    {{shortname}}
                </th>
            ';
            $result = call_user_func($value, $source, $this->lambdaHelper);
            if (strpos($result, '{{') === false) {
                $buffer .= $result;
            } else {
                $buffer .= $this->mustache
                    ->loadLambda((string) $result)
                    ->renderInternal($context);
            }
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= $indent . '                <th class="header text-xs-center">
';
                $buffer .= $indent . '                    ';
                $value = $this->resolveValue($context->find('shortname'), $context);
                $buffer .= call_user_func($this->mustache->getEscape(), $value);
                $buffer .= '
';
                $buffer .= $indent . '                </th>
';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function section30ad4cd62bbcce6b32c5d2ea63ba3dca(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = '
                    <td class="dayblank">&nbsp;</td>
                ';
            $result = call_user_func($value, $source, $this->lambdaHelper);
            if (strpos($result, '{{') === false) {
                $buffer .= $result;
            } else {
                $buffer .= $this->mustache
                    ->loadLambda((string) $result)
                    ->renderInternal($context);
            }
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= $indent . '                    <td class="dayblank">&nbsp;</td>
';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function sectionCce61e3fee851e8b66112494c048a58a(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = ' today';
            $result = call_user_func($value, $source, $this->lambdaHelper);
            if (strpos($result, '{{') === false) {
                $buffer .= $result;
            } else {
                $buffer .= $this->mustache
                    ->loadLambda((string) $result)
                    ->renderInternal($context);
            }
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= ' today';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function section84f949a8191b4daaac38d5ac1997cd7a(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = ' weekend';
            $result = call_user_func($value, $source, $this->lambdaHelper);
            if (strpos($result, '{{') === false) {
                $buffer .= $result;
            } else {
                $buffer .= $this->mustache
                    ->loadLambda((string) $result)
                    ->renderInternal($context);
            }
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= ' weekend';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function section8aa9feca8669cbbfdc6a37548f193ebe(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = ' duration';
            $result = call_user_func($value, $source, $this->lambdaHelper);
            if (strpos($result, '{{') === false) {
                $buffer .= $result;
            } else {
                $buffer .= $this->mustache
                    ->loadLambda((string) $result)
                    ->renderInternal($context);
            }
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= ' duration';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function sectionC090550ce065bf0ee1844d93a2f5c5bc(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = ' duration_{{.}}';
            $result = call_user_func($value, $source, $this->lambdaHelper);
            if (strpos($result, '{{') === false) {
                $buffer .= $result;
            } else {
                $buffer .= $this->mustache
                    ->loadLambda((string) $result)
                    ->renderInternal($context);
            }
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= ' duration_';
                $value = $this->resolveValue($context->last(), $context);
                $buffer .= call_user_func($this->mustache->getEscape(), $value);
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function sectionA7cafb8496be54e32a81ab964df17ff2(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = ' clickable';
            $result = call_user_func($value, $source, $this->lambdaHelper);
            if (strpos($result, '{{') === false) {
                $buffer .= $result;
            } else {
                $buffer .= $this->mustache
                    ->loadLambda((string) $result)
                    ->renderInternal($context);
            }
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= ' clickable';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function sectionAbc64a4adafd3bd12488cd78e701453e(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = '
                                <a data-action="view-day-link" href="{{viewdaylink}}" class="day" title="{{viewdaylinktitle}}">{{mday}}</a>
                            ';
            $result = call_user_func($value, $source, $this->lambdaHelper);
            if (strpos($result, '{{') === false) {
                $buffer .= $result;
            } else {
                $buffer .= $this->mustache
                    ->loadLambda((string) $result)
                    ->renderInternal($context);
            }
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= $indent . '                                <a data-action="view-day-link" href="';
                $value = $this->resolveValue($context->find('viewdaylink'), $context);
                $buffer .= call_user_func($this->mustache->getEscape(), $value);
                $buffer .= '" class="day" title="';
                $value = $this->resolveValue($context->find('viewdaylinktitle'), $context);
                $buffer .= call_user_func($this->mustache->getEscape(), $value);
                $buffer .= '">';
                $value = $this->resolveValue($context->find('mday'), $context);
                $buffer .= call_user_func($this->mustache->getEscape(), $value);
                $buffer .= '</a>
';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function section846f2756bf4f9c39e0b2c0c91f9a8e40(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = '
                                                <li class="events-underway">[{{name}}]</li>
                                            ';
            $result = call_user_func($value, $source, $this->lambdaHelper);
            if (strpos($result, '{{') === false) {
                $buffer .= $result;
            } else {
                $buffer .= $this->mustache
                    ->loadLambda((string) $result)
                    ->renderInternal($context);
            }
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= $indent . '                                                <li class="events-underway">[';
                $value = $this->resolveValue($context->find('name'), $context);
                $buffer .= call_user_func($this->mustache->getEscape(), $value);
                $buffer .= ']</li>
';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function sectionB0a986edc2ba735adb5366b1e1a0119b(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = '
                                                    data-min-day-timestamp="{{.}}"
                                                        ';
            $result = call_user_func($value, $source, $this->lambdaHelper);
            if (strpos($result, '{{') === false) {
                $buffer .= $result;
            } else {
                $buffer .= $this->mustache
                    ->loadLambda((string) $result)
                    ->renderInternal($context);
            }
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= $indent . '                                                    data-min-day-timestamp="';
                $value = $this->resolveValue($context->last(), $context);
                $buffer .= call_user_func($this->mustache->getEscape(), $value);
                $buffer .= '"
';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function sectionD0ffd9f3b1b83657b0e32515538bad6c(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = '
                                                    data-min-day-error="{{.}}"
                                                        ';
            $result = call_user_func($value, $source, $this->lambdaHelper);
            if (strpos($result, '{{') === false) {
                $buffer .= $result;
            } else {
                $buffer .= $this->mustache
                    ->loadLambda((string) $result)
                    ->renderInternal($context);
            }
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= $indent . '                                                    data-min-day-error="';
                $value = $this->resolveValue($context->last(), $context);
                $buffer .= call_user_func($this->mustache->getEscape(), $value);
                $buffer .= '"
';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function section0b23a21d739ac218b244055fc70ceb15(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = '
                                                    data-max-day-timestamp="{{.}}"
                                                        ';
            $result = call_user_func($value, $source, $this->lambdaHelper);
            if (strpos($result, '{{') === false) {
                $buffer .= $result;
            } else {
                $buffer .= $this->mustache
                    ->loadLambda((string) $result)
                    ->renderInternal($context);
            }
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= $indent . '                                                    data-max-day-timestamp="';
                $value = $this->resolveValue($context->last(), $context);
                $buffer .= call_user_func($this->mustache->getEscape(), $value);
                $buffer .= '"
';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function section59a422ef912dbdeda568cf8577d741eb(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = '
                                                    data-max-day-error="{{.}}"
                                                        ';
            $result = call_user_func($value, $source, $this->lambdaHelper);
            if (strpos($result, '{{') === false) {
                $buffer .= $result;
            } else {
                $buffer .= $this->mustache
                    ->loadLambda((string) $result)
                    ->renderInternal($context);
            }
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= $indent . '                                                    data-max-day-error="';
                $value = $this->resolveValue($context->last(), $context);
                $buffer .= call_user_func($this->mustache->getEscape(), $value);
                $buffer .= '"
';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function sectionA78b9d2c7b189fe356864db004e9d04f(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = '
                                                    draggable="true"
                                                    data-drag-type="move"
                                                        {{#mindaytimestamp}}
                                                    data-min-day-timestamp="{{.}}"
                                                        {{/mindaytimestamp}}
                                                        {{#mindayerror}}
                                                    data-min-day-error="{{.}}"
                                                        {{/mindayerror}}
                                                        {{#maxdaytimestamp}}
                                                    data-max-day-timestamp="{{.}}"
                                                        {{/maxdaytimestamp}}
                                                        {{#maxdayerror}}
                                                    data-max-day-error="{{.}}"
                                                        {{/maxdayerror}}
                                                    ';
            $result = call_user_func($value, $source, $this->lambdaHelper);
            if (strpos($result, '{{') === false) {
                $buffer .= $result;
            } else {
                $buffer .= $this->mustache
                    ->loadLambda((string) $result)
                    ->renderInternal($context);
            }
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= $indent . '                                                    draggable="true"
';
                $buffer .= $indent . '                                                    data-drag-type="move"
';
                // 'mindaytimestamp' section
                $value = $context->find('mindaytimestamp');
                $buffer .= $this->sectionB0a986edc2ba735adb5366b1e1a0119b($context, $indent, $value);
                // 'mindayerror' section
                $value = $context->find('mindayerror');
                $buffer .= $this->sectionD0ffd9f3b1b83657b0e32515538bad6c($context, $indent, $value);
                // 'maxdaytimestamp' section
                $value = $context->find('maxdaytimestamp');
                $buffer .= $this->section0b23a21d739ac218b244055fc70ceb15($context, $indent, $value);
                // 'maxdayerror' section
                $value = $context->find('maxdayerror');
                $buffer .= $this->section59a422ef912dbdeda568cf8577d741eb($context, $indent, $value);
                $buffer .= $indent . '                                                    ';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function section760250d469a9b98ba4e884e3f03d66ec(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = '
                                            {{#underway}}
                                                <li class="events-underway">[{{name}}]</li>
                                            {{/underway}}
                                            {{^underway}}
                                                <li data-region="event-item"
                                                    data-eventtype-{{normalisedeventtype}}="1"
                                                    class="calendar_event_{{normalisedeventtype}}"
                                                    {{#draggable}}
                                                    draggable="true"
                                                    data-drag-type="move"
                                                        {{#mindaytimestamp}}
                                                    data-min-day-timestamp="{{.}}"
                                                        {{/mindaytimestamp}}
                                                        {{#mindayerror}}
                                                    data-min-day-error="{{.}}"
                                                        {{/mindayerror}}
                                                        {{#maxdaytimestamp}}
                                                    data-max-day-timestamp="{{.}}"
                                                        {{/maxdaytimestamp}}
                                                        {{#maxdayerror}}
                                                    data-max-day-error="{{.}}"
                                                        {{/maxdayerror}}
                                                    {{/draggable}}>

                                                    <a data-action="view-event" data-event-id="{{id}}" href="{{url}}" title="{{name}}">
                                                        {{> core_calendar/event_icon}}
                                                        {{name}}
                                                    </a>
                                                </li>
                                            {{/underway}}
                                        ';
            $result = call_user_func($value, $source, $this->lambdaHelper);
            if (strpos($result, '{{') === false) {
                $buffer .= $result;
            } else {
                $buffer .= $this->mustache
                    ->loadLambda((string) $result)
                    ->renderInternal($context);
            }
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                // 'underway' section
                $value = $context->find('underway');
                $buffer .= $this->section846f2756bf4f9c39e0b2c0c91f9a8e40($context, $indent, $value);
                // 'underway' inverted section
                $value = $context->find('underway');
                if (empty($value)) {
                    
                    $buffer .= $indent . '                                                <li data-region="event-item"
';
                    $buffer .= $indent . '                                                    data-eventtype-';
                    $value = $this->resolveValue($context->find('normalisedeventtype'), $context);
                    $buffer .= call_user_func($this->mustache->getEscape(), $value);
                    $buffer .= '="1"
';
                    $buffer .= $indent . '                                                    class="calendar_event_';
                    $value = $this->resolveValue($context->find('normalisedeventtype'), $context);
                    $buffer .= call_user_func($this->mustache->getEscape(), $value);
                    $buffer .= '"
';
                    // 'draggable' section
                    $value = $context->find('draggable');
                    $buffer .= $this->sectionA78b9d2c7b189fe356864db004e9d04f($context, $indent, $value);
                    $buffer .= '>
';
                    $buffer .= $indent . '
';
                    $buffer .= $indent . '                                                    <a data-action="view-event" data-event-id="';
                    $value = $this->resolveValue($context->find('id'), $context);
                    $buffer .= call_user_func($this->mustache->getEscape(), $value);
                    $buffer .= '" href="';
                    $value = $this->resolveValue($context->find('url'), $context);
                    $buffer .= call_user_func($this->mustache->getEscape(), $value);
                    $buffer .= '" title="';
                    $value = $this->resolveValue($context->find('name'), $context);
                    $buffer .= call_user_func($this->mustache->getEscape(), $value);
                    $buffer .= '">
';
                    if ($partial = $this->mustache->loadPartial('core_calendar/event_icon')) {
                        $buffer .= $partial->renderInternal($context, $indent . '                                                        ');
                    }
                    $buffer .= $indent . '                                                        ';
                    $value = $this->resolveValue($context->find('name'), $context);
                    $buffer .= call_user_func($this->mustache->getEscape(), $value);
                    $buffer .= '
';
                    $buffer .= $indent . '                                                    </a>
';
                    $buffer .= $indent . '                                                </li>
';
                }
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function section20464f2c405e8ad79b045e0ea57c5477(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = '
                                <div data-region="day-content">
                                    <ul>
                                        {{#events}}
                                            {{#underway}}
                                                <li class="events-underway">[{{name}}]</li>
                                            {{/underway}}
                                            {{^underway}}
                                                <li data-region="event-item"
                                                    data-eventtype-{{normalisedeventtype}}="1"
                                                    class="calendar_event_{{normalisedeventtype}}"
                                                    {{#draggable}}
                                                    draggable="true"
                                                    data-drag-type="move"
                                                        {{#mindaytimestamp}}
                                                    data-min-day-timestamp="{{.}}"
                                                        {{/mindaytimestamp}}
                                                        {{#mindayerror}}
                                                    data-min-day-error="{{.}}"
                                                        {{/mindayerror}}
                                                        {{#maxdaytimestamp}}
                                                    data-max-day-timestamp="{{.}}"
                                                        {{/maxdaytimestamp}}
                                                        {{#maxdayerror}}
                                                    data-max-day-error="{{.}}"
                                                        {{/maxdayerror}}
                                                    {{/draggable}}>

                                                    <a data-action="view-event" data-event-id="{{id}}" href="{{url}}" title="{{name}}">
                                                        {{> core_calendar/event_icon}}
                                                        {{name}}
                                                    </a>
                                                </li>
                                            {{/underway}}
                                        {{/events}}
                                    </ul>
                                </div>
                            ';
            $result = call_user_func($value, $source, $this->lambdaHelper);
            if (strpos($result, '{{') === false) {
                $buffer .= $result;
            } else {
                $buffer .= $this->mustache
                    ->loadLambda((string) $result)
                    ->renderInternal($context);
            }
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= $indent . '                                <div data-region="day-content">
';
                $buffer .= $indent . '                                    <ul>
';
                // 'events' section
                $value = $context->find('events');
                $buffer .= $this->section760250d469a9b98ba4e884e3f03d66ec($context, $indent, $value);
                $buffer .= $indent . '                                    </ul>
';
                $buffer .= $indent . '                                </div>
';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function sectionEe14cf9fd4744594e6ed600f9db3f39d(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = '
                    <td class="day text-sm-center text-md-left{{!
                            }}{{#istoday}} today{{/istoday}}{{!
                            }}{{#isweekend}} weekend{{/isweekend}}{{!
                            }}{{#durationevents.0}} duration{{/durationevents.0}}{{!
                            }}{{#durationevents}} duration_{{.}}{{/durationevents}}{{!
                            }}{{#defaulteventcontext}} clickable{{/defaulteventcontext}}{{!
                        }}"
                        data-day-timestamp="{{timestamp}}"
                        data-drop-zone="month-view-day"
                        data-region="day"
                        data-new-event-timestamp="{{neweventtimestamp}}">
                        <div class="d-none d-md-block hidden-phone text-xs-center">
                            {{#hasevents}}
                                <a data-action="view-day-link" href="{{viewdaylink}}" class="day" title="{{viewdaylinktitle}}">{{mday}}</a>
                            {{/hasevents}}
                            {{^hasevents}}
                                {{mday}}
                            {{/hasevents}}
                            {{#hasevents}}
                                <div data-region="day-content">
                                    <ul>
                                        {{#events}}
                                            {{#underway}}
                                                <li class="events-underway">[{{name}}]</li>
                                            {{/underway}}
                                            {{^underway}}
                                                <li data-region="event-item"
                                                    data-eventtype-{{normalisedeventtype}}="1"
                                                    class="calendar_event_{{normalisedeventtype}}"
                                                    {{#draggable}}
                                                    draggable="true"
                                                    data-drag-type="move"
                                                        {{#mindaytimestamp}}
                                                    data-min-day-timestamp="{{.}}"
                                                        {{/mindaytimestamp}}
                                                        {{#mindayerror}}
                                                    data-min-day-error="{{.}}"
                                                        {{/mindayerror}}
                                                        {{#maxdaytimestamp}}
                                                    data-max-day-timestamp="{{.}}"
                                                        {{/maxdaytimestamp}}
                                                        {{#maxdayerror}}
                                                    data-max-day-error="{{.}}"
                                                        {{/maxdayerror}}
                                                    {{/draggable}}>

                                                    <a data-action="view-event" data-event-id="{{id}}" href="{{url}}" title="{{name}}">
                                                        {{> core_calendar/event_icon}}
                                                        {{name}}
                                                    </a>
                                                </li>
                                            {{/underway}}
                                        {{/events}}
                                    </ul>
                                </div>
                            {{/hasevents}}
                        </div>
                        <div class="d-md-none hidden-desktop hidden-tablet">
                            {{#hasevents}}
                                <a data-action="view-day-link" href="{{viewdaylink}}" class="day" title="{{viewdaylinktitle}}">{{mday}}</a>
                            {{/hasevents}}
                            {{^hasevents}}
                                <div data-region="day-content">
                                    {{mday}}
                                </div>
                            {{/hasevents}}
                        </div>
                    </td>
                ';
            $result = call_user_func($value, $source, $this->lambdaHelper);
            if (strpos($result, '{{') === false) {
                $buffer .= $result;
            } else {
                $buffer .= $this->mustache
                    ->loadLambda((string) $result)
                    ->renderInternal($context);
            }
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= $indent . '                    <td class="day text-sm-center text-md-left';
                // 'istoday' section
                $value = $context->find('istoday');
                $buffer .= $this->sectionCce61e3fee851e8b66112494c048a58a($context, $indent, $value);
                // 'isweekend' section
                $value = $context->find('isweekend');
                $buffer .= $this->section84f949a8191b4daaac38d5ac1997cd7a($context, $indent, $value);
                // 'durationevents.0' section
                $value = $context->findDot('durationevents.0');
                $buffer .= $this->section8aa9feca8669cbbfdc6a37548f193ebe($context, $indent, $value);
                // 'durationevents' section
                $value = $context->find('durationevents');
                $buffer .= $this->sectionC090550ce065bf0ee1844d93a2f5c5bc($context, $indent, $value);
                // 'defaulteventcontext' section
                $value = $context->find('defaulteventcontext');
                $buffer .= $this->sectionA7cafb8496be54e32a81ab964df17ff2($context, $indent, $value);
                $buffer .= '"
';
                $buffer .= $indent . '                        data-day-timestamp="';
                $value = $this->resolveValue($context->find('timestamp'), $context);
                $buffer .= call_user_func($this->mustache->getEscape(), $value);
                $buffer .= '"
';
                $buffer .= $indent . '                        data-drop-zone="month-view-day"
';
                $buffer .= $indent . '                        data-region="day"
';
                $buffer .= $indent . '                        data-new-event-timestamp="';
                $value = $this->resolveValue($context->find('neweventtimestamp'), $context);
                $buffer .= call_user_func($this->mustache->getEscape(), $value);
                $buffer .= '">
';
                $buffer .= $indent . '                        <div class="d-none d-md-block hidden-phone text-xs-center">
';
                // 'hasevents' section
                $value = $context->find('hasevents');
                $buffer .= $this->sectionAbc64a4adafd3bd12488cd78e701453e($context, $indent, $value);
                // 'hasevents' inverted section
                $value = $context->find('hasevents');
                if (empty($value)) {
                    
                    $buffer .= $indent . '                                ';
                    $value = $this->resolveValue($context->find('mday'), $context);
                    $buffer .= call_user_func($this->mustache->getEscape(), $value);
                    $buffer .= '
';
                }
                // 'hasevents' section
                $value = $context->find('hasevents');
                $buffer .= $this->section20464f2c405e8ad79b045e0ea57c5477($context, $indent, $value);
                $buffer .= $indent . '                        </div>
';
                $buffer .= $indent . '                        <div class="d-md-none hidden-desktop hidden-tablet">
';
                // 'hasevents' section
                $value = $context->find('hasevents');
                $buffer .= $this->sectionAbc64a4adafd3bd12488cd78e701453e($context, $indent, $value);
                // 'hasevents' inverted section
                $value = $context->find('hasevents');
                if (empty($value)) {
                    
                    $buffer .= $indent . '                                <div data-region="day-content">
';
                    $buffer .= $indent . '                                    ';
                    $value = $this->resolveValue($context->find('mday'), $context);
                    $buffer .= call_user_func($this->mustache->getEscape(), $value);
                    $buffer .= '
';
                    $buffer .= $indent . '                                </div>
';
                }
                $buffer .= $indent . '                        </div>
';
                $buffer .= $indent . '                    </td>
';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function sectionA07fd3399f7eec91546450dc074994d7(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = '
            <tr data-region="month-view-week">
                {{#prepadding}}
                    <td class="dayblank">&nbsp;</td>
                {{/prepadding}}
                {{#days}}
                    <td class="day text-sm-center text-md-left{{!
                            }}{{#istoday}} today{{/istoday}}{{!
                            }}{{#isweekend}} weekend{{/isweekend}}{{!
                            }}{{#durationevents.0}} duration{{/durationevents.0}}{{!
                            }}{{#durationevents}} duration_{{.}}{{/durationevents}}{{!
                            }}{{#defaulteventcontext}} clickable{{/defaulteventcontext}}{{!
                        }}"
                        data-day-timestamp="{{timestamp}}"
                        data-drop-zone="month-view-day"
                        data-region="day"
                        data-new-event-timestamp="{{neweventtimestamp}}">
                        <div class="d-none d-md-block hidden-phone text-xs-center">
                            {{#hasevents}}
                                <a data-action="view-day-link" href="{{viewdaylink}}" class="day" title="{{viewdaylinktitle}}">{{mday}}</a>
                            {{/hasevents}}
                            {{^hasevents}}
                                {{mday}}
                            {{/hasevents}}
                            {{#hasevents}}
                                <div data-region="day-content">
                                    <ul>
                                        {{#events}}
                                            {{#underway}}
                                                <li class="events-underway">[{{name}}]</li>
                                            {{/underway}}
                                            {{^underway}}
                                                <li data-region="event-item"
                                                    data-eventtype-{{normalisedeventtype}}="1"
                                                    class="calendar_event_{{normalisedeventtype}}"
                                                    {{#draggable}}
                                                    draggable="true"
                                                    data-drag-type="move"
                                                        {{#mindaytimestamp}}
                                                    data-min-day-timestamp="{{.}}"
                                                        {{/mindaytimestamp}}
                                                        {{#mindayerror}}
                                                    data-min-day-error="{{.}}"
                                                        {{/mindayerror}}
                                                        {{#maxdaytimestamp}}
                                                    data-max-day-timestamp="{{.}}"
                                                        {{/maxdaytimestamp}}
                                                        {{#maxdayerror}}
                                                    data-max-day-error="{{.}}"
                                                        {{/maxdayerror}}
                                                    {{/draggable}}>

                                                    <a data-action="view-event" data-event-id="{{id}}" href="{{url}}" title="{{name}}">
                                                        {{> core_calendar/event_icon}}
                                                        {{name}}
                                                    </a>
                                                </li>
                                            {{/underway}}
                                        {{/events}}
                                    </ul>
                                </div>
                            {{/hasevents}}
                        </div>
                        <div class="d-md-none hidden-desktop hidden-tablet">
                            {{#hasevents}}
                                <a data-action="view-day-link" href="{{viewdaylink}}" class="day" title="{{viewdaylinktitle}}">{{mday}}</a>
                            {{/hasevents}}
                            {{^hasevents}}
                                <div data-region="day-content">
                                    {{mday}}
                                </div>
                            {{/hasevents}}
                        </div>
                    </td>
                {{/days}}
                {{#postpadding}}
                    <td class="dayblank">&nbsp;</td>
                {{/postpadding}}
            </tr>
        ';
            $result = call_user_func($value, $source, $this->lambdaHelper);
            if (strpos($result, '{{') === false) {
                $buffer .= $result;
            } else {
                $buffer .= $this->mustache
                    ->loadLambda((string) $result)
                    ->renderInternal($context);
            }
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= $indent . '            <tr data-region="month-view-week">
';
                // 'prepadding' section
                $value = $context->find('prepadding');
                $buffer .= $this->section30ad4cd62bbcce6b32c5d2ea63ba3dca($context, $indent, $value);
                // 'days' section
                $value = $context->find('days');
                $buffer .= $this->sectionEe14cf9fd4744594e6ed600f9db3f39d($context, $indent, $value);
                // 'postpadding' section
                $value = $context->find('postpadding');
                $buffer .= $this->section30ad4cd62bbcce6b32c5d2ea63ba3dca($context, $indent, $value);
                $buffer .= $indent . '            </tr>
';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function section453db4c1e03f6f1d21b1aa1f4ea3d3c4(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = '
    require([
    \'jquery\',
    \'core_calendar/month_view_drag_drop\',
    \'core_calendar/selectors\',
    \'core_calendar/events\',
    ], function(
    $,
    DragDrop,
    CalendarSelectors,
    CalendarEvents
    ) {
    var root = $(\'#month-detailed-{{uniqid}}\');
    DragDrop.init(root);

    $(\'body\').on(CalendarEvents.filterChanged, function(e, data) {
    M.util.js_pending("month-detailed-{{uniqid}}-filterChanged");
    // A filter value has been changed.
    // Find all matching cells in the popover data, and hide them.
    var target = $("#month-detailed-{{uniqid}}").find(CalendarSelectors.eventType[data.type]);

    var transitionPromise = $.Deferred();
    if (data.hidden) {
    transitionPromise.then(function() {
    return target.slideUp(\'fast\').promise();
    });
    } else {
    transitionPromise.then(function() {
    return target.slideDown(\'fast\').promise();
    });
    }

    transitionPromise.then(function() {
    M.util.js_complete("month-detailed-{{uniqid}}-filterChanged");

    return;
    });

    transitionPromise.resolve();
    });
    });
';
            $result = call_user_func($value, $source, $this->lambdaHelper);
            if (strpos($result, '{{') === false) {
                $buffer .= $result;
            } else {
                $buffer .= $this->mustache
                    ->loadLambda((string) $result)
                    ->renderInternal($context);
            }
        } elseif (!empty($value)) {
            $values = $this->isIterable($value) ? $value : array($value);
            foreach ($values as $value) {
                $context->push($value);
                
                $buffer .= $indent . '    require([
';
                $buffer .= $indent . '    \'jquery\',
';
                $buffer .= $indent . '    \'core_calendar/month_view_drag_drop\',
';
                $buffer .= $indent . '    \'core_calendar/selectors\',
';
                $buffer .= $indent . '    \'core_calendar/events\',
';
                $buffer .= $indent . '    ], function(
';
                $buffer .= $indent . '    $,
';
                $buffer .= $indent . '    DragDrop,
';
                $buffer .= $indent . '    CalendarSelectors,
';
                $buffer .= $indent . '    CalendarEvents
';
                $buffer .= $indent . '    ) {
';
                $buffer .= $indent . '    var root = $(\'#month-detailed-';
                $value = $this->resolveValue($context->find('uniqid'), $context);
                $buffer .= call_user_func($this->mustache->getEscape(), $value);
                $buffer .= '\');
';
                $buffer .= $indent . '    DragDrop.init(root);
';
                $buffer .= $indent . '
';
                $buffer .= $indent . '    $(\'body\').on(CalendarEvents.filterChanged, function(e, data) {
';
                $buffer .= $indent . '    M.util.js_pending("month-detailed-';
                $value = $this->resolveValue($context->find('uniqid'), $context);
                $buffer .= call_user_func($this->mustache->getEscape(), $value);
                $buffer .= '-filterChanged");
';
                $buffer .= $indent . '    // A filter value has been changed.
';
                $buffer .= $indent . '    // Find all matching cells in the popover data, and hide them.
';
                $buffer .= $indent . '    var target = $("#month-detailed-';
                $value = $this->resolveValue($context->find('uniqid'), $context);
                $buffer .= call_user_func($this->mustache->getEscape(), $value);
                $buffer .= '").find(CalendarSelectors.eventType[data.type]);
';
                $buffer .= $indent . '
';
                $buffer .= $indent . '    var transitionPromise = $.Deferred();
';
                $buffer .= $indent . '    if (data.hidden) {
';
                $buffer .= $indent . '    transitionPromise.then(function() {
';
                $buffer .= $indent . '    return target.slideUp(\'fast\').promise();
';
                $buffer .= $indent . '    });
';
                $buffer .= $indent . '    } else {
';
                $buffer .= $indent . '    transitionPromise.then(function() {
';
                $buffer .= $indent . '    return target.slideDown(\'fast\').promise();
';
                $buffer .= $indent . '    });
';
                $buffer .= $indent . '    }
';
                $buffer .= $indent . '
';
                $buffer .= $indent . '    transitionPromise.then(function() {
';
                $buffer .= $indent . '    M.util.js_complete("month-detailed-';
                $value = $this->resolveValue($context->find('uniqid'), $context);
                $buffer .= call_user_func($this->mustache->getEscape(), $value);
                $buffer .= '-filterChanged");
';
                $buffer .= $indent . '
';
                $buffer .= $indent . '    return;
';
                $buffer .= $indent . '    });
';
                $buffer .= $indent . '
';
                $buffer .= $indent . '    transitionPromise.resolve();
';
                $buffer .= $indent . '    });
';
                $buffer .= $indent . '    });
';
                $context->pop();
            }
        }
    
        return $buffer;
    }

}

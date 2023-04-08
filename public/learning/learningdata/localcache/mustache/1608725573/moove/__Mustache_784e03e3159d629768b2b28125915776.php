<?php

class __Mustache_784e03e3159d629768b2b28125915776 extends Mustache_Template
{
    private $lambdaHelper;

    public function renderInternal(Mustache_Context $context, $indent = '')
    {
        $this->lambdaHelper = new Mustache_LambdaHelper($this->mustache, $context);
        $buffer = '';

        $buffer .= $indent . '<div class="float-right border mb-2 p-2">
';
        $buffer .= $indent . '<form class="user-competency-course-navigation">
';
        $buffer .= $indent . '<input type="hidden" name="courseid" value="';
        $value = $this->resolveValue($context->find('courseid'), $context);
        $buffer .= call_user_func($this->mustache->getEscape(), $value);
        $buffer .= '"/>
';
        $buffer .= $indent . '<input type="hidden" name="mod" value="';
        $value = $this->resolveValue($context->find('moduleid'), $context);
        $buffer .= call_user_func($this->mustache->getEscape(), $value);
        $buffer .= '"/>
';
        // 'hasmodules' section
        $value = $context->find('hasmodules');
        $buffer .= $this->sectionAe83013ea0b7d38b870c63a31fe84f3d($context, $indent, $value);
        $buffer .= $indent . '</form>
';
        $buffer .= $indent . '</div>
';
        // 'js' section
        $value = $context->find('js');
        $buffer .= $this->sectionA5ffd35f601b7a3ae1396ad0f281f3f1($context, $indent, $value);

        return $buffer;
    }

    private function section0cd16079f653f91505cf229e72912fe4(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = 'filterbyactivity, tool_lp';
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
                
                $buffer .= 'filterbyactivity, tool_lp';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function sectionC877874b20aed109ed5be9bdc0ef9c49(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = 'selected="selected"';
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
                
                $buffer .= 'selected="selected"';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function section1a4a96f8808adbdf9bcf1bdb4ee2b2ec(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = '
<option value="{{id}}" {{#selected}}selected="selected"{{/selected}}>{{name}}</option>
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
                
                $buffer .= $indent . '<option value="';
                $value = $this->resolveValue($context->find('id'), $context);
                $buffer .= call_user_func($this->mustache->getEscape(), $value);
                $buffer .= '" ';
                // 'selected' section
                $value = $context->find('selected');
                $buffer .= $this->sectionC877874b20aed109ed5be9bdc0ef9c49($context, $indent, $value);
                $buffer .= '>';
                $value = $this->resolveValue($context->find('name'), $context);
                $buffer .= call_user_func($this->mustache->getEscape(), $value);
                $buffer .= '</option>
';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function sectionAe83013ea0b7d38b870c63a31fe84f3d(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = '
<span>
<label for="module-nav-{{uniqid}}" class="accesshide">{{#str}}filterbyactivity, tool_lp{{/str}}</label>
<select id="module-nav-{{uniqid}}">
{{#modules}}
<option value="{{id}}" {{#selected}}selected="selected"{{/selected}}>{{name}}</option>
{{/modules}}
</select>
</span>
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
                
                $buffer .= $indent . '<span>
';
                $buffer .= $indent . '<label for="module-nav-';
                $value = $this->resolveValue($context->find('uniqid'), $context);
                $buffer .= call_user_func($this->mustache->getEscape(), $value);
                $buffer .= '" class="accesshide">';
                // 'str' section
                $value = $context->find('str');
                $buffer .= $this->section0cd16079f653f91505cf229e72912fe4($context, $indent, $value);
                $buffer .= '</label>
';
                $buffer .= $indent . '<select id="module-nav-';
                $value = $this->resolveValue($context->find('uniqid'), $context);
                $buffer .= call_user_func($this->mustache->getEscape(), $value);
                $buffer .= '">
';
                // 'modules' section
                $value = $context->find('modules');
                $buffer .= $this->section1a4a96f8808adbdf9bcf1bdb4ee2b2ec($context, $indent, $value);
                $buffer .= $indent . '</select>
';
                $buffer .= $indent . '</span>
';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function sectionEe4f27ac70ec58f9ae9eee2c28213dd2(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = '{{# str }}filterbyactivity, tool_lp{{/ str }}';
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
                
                // 'str' section
                $value = $context->find('str');
                $buffer .= $this->section0cd16079f653f91505cf229e72912fe4($context, $indent, $value);
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function sectionFd006758845fa96f8923358efa7219f9(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = '
    autocomplete.enhance(\'#module-nav-{{uniqid}}\', false, false, {{# quote }}{{# str }}filterbyactivity, tool_lp{{/ str }}{{/ quote }});
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
                
                $buffer .= $indent . '    autocomplete.enhance(\'#module-nav-';
                $value = $this->resolveValue($context->find('uniqid'), $context);
                $buffer .= call_user_func($this->mustache->getEscape(), $value);
                $buffer .= '\', false, false, ';
                // 'quote' section
                $value = $context->find('quote');
                $buffer .= $this->sectionEe4f27ac70ec58f9ae9eee2c28213dd2($context, $indent, $value);
                $buffer .= ');
';
                $context->pop();
            }
        }
    
        return $buffer;
    }

    private function sectionA5ffd35f601b7a3ae1396ad0f281f3f1(Mustache_Context $context, $indent, $value)
    {
        $buffer = '';
    
        if (!is_string($value) && is_callable($value)) {
            $source = '
require([\'core/form-autocomplete\', \'tool_lp/module_navigation\'], function(autocomplete, nav) {
    (new nav(\'#module-nav-{{uniqid}}\', \'{{baseurl}}\', {{courseid}}, {{moduleid}}));
{{#hasmodules}}
    autocomplete.enhance(\'#module-nav-{{uniqid}}\', false, false, {{# quote }}{{# str }}filterbyactivity, tool_lp{{/ str }}{{/ quote }});
{{/hasmodules}}
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
                
                $buffer .= $indent . 'require([\'core/form-autocomplete\', \'tool_lp/module_navigation\'], function(autocomplete, nav) {
';
                $buffer .= $indent . '    (new nav(\'#module-nav-';
                $value = $this->resolveValue($context->find('uniqid'), $context);
                $buffer .= call_user_func($this->mustache->getEscape(), $value);
                $buffer .= '\', \'';
                $value = $this->resolveValue($context->find('baseurl'), $context);
                $buffer .= call_user_func($this->mustache->getEscape(), $value);
                $buffer .= '\', ';
                $value = $this->resolveValue($context->find('courseid'), $context);
                $buffer .= call_user_func($this->mustache->getEscape(), $value);
                $buffer .= ', ';
                $value = $this->resolveValue($context->find('moduleid'), $context);
                $buffer .= call_user_func($this->mustache->getEscape(), $value);
                $buffer .= '));
';
                // 'hasmodules' section
                $value = $context->find('hasmodules');
                $buffer .= $this->sectionFd006758845fa96f8923358efa7219f9($context, $indent, $value);
                $buffer .= $indent . '});
';
                $context->pop();
            }
        }
    
        return $buffer;
    }

}

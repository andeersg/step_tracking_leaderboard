<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class Attribute extends AbstractExtension
{
    protected $storage = [];

    public function getFunctions()
    {
        return [
            new TwigFunction('attribute', [$this, 'attribute']),
        ];
    }

    public function attribute($attributes = [])
    {
        foreach ($attributes as $name => $value) {
            $this->offsetSet($name, $value);
        }
    }

    public function offsetGet($name)
    {
        if (isset($this->storage[$name])) {
          return $this->storage[$name];
        }
    }

    public function offsetSet($name, $value)
    {
        $this->storage[$name] = $this->createAttributeValue($name, $value);
    }

    protected function createAttributeValue($name, $value) {
        // If the value is already an AttributeValueBase object,
        // return a new instance of the same class, but with the new name.
        if ($value instanceof AttributeValueBase) {
          $class = get_class($value);
          return new $class($name, $value->value());
        }
        // An array value or 'class' attribute name are forced to always be an
        // AttributeArray value for consistency.
        if ($name == 'class' && !is_array($value)) {
          // Cast the value to string in case it implements MarkupInterface.
          $value = [(string) $value];
        }
        if (is_array($value)) {
          // Cast the value to an array if the value was passed in as a string.
          // @todo Decide to fix all the broken instances of class as a string
          // in core or cast them.
          $value = new AttributeArray($name, $value);
        }
        elseif (is_bool($value)) {
          $value = new AttributeBoolean($name, $value);
        }
        // As a development aid, we allow the value to be a safe string object.
        elseif (!is_object($value)) {
          $value = new AttributeString($name, $value);
        }
        return $value;
      }

      public function offsetUnset($name) {
        unset($this->storage[$name]);
      }
    
      /**
       * {@inheritdoc}
       */
      public function offsetExists($name) {
        return isset($this->storage[$name]);
      }
    
      /**
       * Adds classes or merges them on to array of existing CSS classes.
       *
       * @param string|array ...
       *   CSS classes to add to the class attribute array.
       *
       * @return $this
       */
      public function addClass() {
        $args = func_get_args();
        if ($args) {
          $classes = [];
          foreach ($args as $arg) {
            // Merge the values passed in from the classes array.
            // The argument is cast to an array to support comma separated single
            // values or one or more array arguments.
            $classes = array_merge($classes, (array) $arg);
          }
    
          // Merge if there are values, just add them otherwise.
          if (isset($this->storage['class']) && $this->storage['class'] instanceof AttributeArray) {
            // Merge the values passed in from the class value array.
            $classes = array_merge($this->storage['class']->value(), $classes);
            $this->storage['class']->exchangeArray($classes);
          }
          else {
            $this->offsetSet('class', $classes);
          }
        }
    
        return $this;
      }
    
      public function setAttribute($attribute, $value) {
        $this->offsetSet($attribute, $value);
    
        return $this;
      }
 
      public function hasAttribute($name) {
        return array_key_exists($name, $this->storage);
      }
    
      public function removeAttribute() {
        $args = func_get_args();
        foreach ($args as $arg) {
          // Support arrays or multiple arguments.
          if (is_array($arg)) {
            foreach ($arg as $value) {
              unset($this->storage[$value]);
            }
          }
          else {
            unset($this->storage[$arg]);
          }
        }
    
        return $this;
      }
    
      /**
       * Removes argument values from array of existing CSS classes.
       *
       * @param string|array ...
       *   CSS classes to remove from the class attribute array.
       *
       * @return $this
       */
      public function removeClass() {
        // With no class attribute, there is no need to remove.
        if (isset($this->storage['class']) && $this->storage['class'] instanceof AttributeArray) {
          $args = func_get_args();
          $classes = [];
          foreach ($args as $arg) {
            // Merge the values passed in from the classes array.
            // The argument is cast to an array to support comma separated single
            // values or one or more array arguments.
            $classes = array_merge($classes, (array) $arg);
          }
    
          // Remove the values passed in from the value array. Use array_values() to
          // ensure that the array index remains sequential.
          $classes = array_values(array_diff($this->storage['class']->value(), $classes));
          $this->storage['class']->exchangeArray($classes);
        }
        return $this;
      }
    
      /**
       * Gets the class attribute value if set.
       *
       * This method is implemented to take precedence over hasClass() for Twig 2.0.
       *
       * @return \Drupal\Core\Template\AttributeValueBase
       *   The class attribute value if set.
       *
       * @see twig_get_attribute()
       */
      public function getClass() {
        return $this->offsetGet('class');
      }
    
      /**
       * Checks if the class array has the given CSS class.
       *
       * @param string $class
       *   The CSS class to check for.
       *
       * @return bool
       *   Returns TRUE if the class exists, or FALSE otherwise.
       */
      public function hasClass($class) {
        if (isset($this->storage['class']) && $this->storage['class'] instanceof AttributeArray) {
          return in_array($class, $this->storage['class']->value());
        }
        else {
          return FALSE;
        }
      }
    
      public function __toString() {
        $return = '';
        /** @var \Drupal\Core\Template\AttributeValueBase $value */
        foreach ($this->storage as $name => $value) {
          $rendered = $value->render();
          if ($rendered) {
            $return .= ' ' . $rendered;
          }
        }
        return $return;
      }
    
      public function toArray() {
        $return = [];
        foreach ($this->storage as $name => $value) {
          $return[$name] = $value->value();
        }
    
        return $return;
      }
    
      public function __clone() {
        foreach ($this->storage as $name => $value) {
          $this->storage[$name] = clone $value;
        }
      }
    
      public function getIterator() {
        return new \ArrayIterator($this->storage);
      }
    
      public function storage() {
        return $this->storage;
      }
    
      public function jsonSerialize() {
        return (string) $this;
      }
    
      public function merge(Attribute $collection) {
        $merged_attributes = NestedArray::mergeDeep($this->toArray(), $collection->toArray());
        foreach ($merged_attributes as $name => $value) {
          $this->storage[$name] = $this->createAttributeValue($name, $value);
        }
        return $this;
      }

}
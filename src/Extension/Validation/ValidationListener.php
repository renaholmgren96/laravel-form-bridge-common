<?php namespace Barryvdh\Form\Extension\Validation;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;

class ValidationListener implements EventSubscriberInterface
{
    protected $validator;

    public function __construct(ValidationFactory $validator)
    {
        $this->validator = $validator;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return array(
                FormEvents::POST_SUBMIT => 'validateRules',
            );
    }

    public function validateRules(FormEvent $event)
    {
        $rules = [];
        $data = [];

        if ($event->getForm()->isRoot()) {
            $root = $event->getForm();
            foreach ($root->all() as $form) {
                $config = $form->getConfig();
                $name = $form->getName();
                $data[$name] =  $form->getData();

                if ($config->hasOption('rules') ) {

                    $rule = $config->getOption('rules');
                    $innerType = $form->getConfig()->getType()->getInnerType();
                    $rule = $this->addTypeRules($innerType, $rule);

                    if ($innerType instanceof CollectionType) {
                        $name .= '.*';
                    }
                    $rules[$name] = $rule;
                }
            }

            $validator = $this->validator->make($data, $rules);
            if ($validator->fails()) {
                foreach ($validator->getMessageBag()->toArray() as $name => $messages) {
                    foreach ($messages as $message) {
                        $form = $this->getByDotted($root, $name);
                        $form->addError(new FormError($message));
                    }
                }
            }
        }
    }


    protected function getByDotted(FormInterface $form, $name)
    {
        $parts = explode('.', $name);

        while($name = array_shift($parts)) {
            $form = $form->get($name);
        }

        return $form;
    }

    protected function addTypeRules(FormTypeInterface $type, array $rules)
    {
        if (
            ($type instanceof NumberType || $type instanceof IntegerType)
            && !in_array('numeric', $rules)
        ) {
            $rules[] = 'numeric';
        }

        if (
            ($type instanceof EmailType)
            && !in_array('email', $rules)
        ) {
            $rules[] = 'email';
        }

        if (
            ($type instanceof TextType || $type instanceof TextareaType)
            && !in_array('string', $rules)
        ) {
            $rules[] = 'string';
        }

        return $rules;
    }
}

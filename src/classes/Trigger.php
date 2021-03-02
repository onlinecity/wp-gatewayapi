<?php

namespace OnlineCity\GatewayAPI;

class Trigger {

    /**
     * Id
     *
     * @var string
     */
    protected $id = '';

    /**
     * Name
     *
     * @var string
     */
    protected $name = '';

    /**
     * Group
     *
     * @var string
     */
    protected $group = '';

    /**
     * Short description of the Trigger
     * No html tags allowed. Keep it tweet-short.
     *
     * @var string
     */
    protected $description = '';

    /**
     * Action to hook
     * @var string
     */
    protected $action = null;



    /**
     * Create a new trigger instance.
     *
     * @param  array  $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Fill the model with an array of attributes.
     *
     * @param  array  $attributes
     * @return $this
     */
    public function fill(array $attributes)
    {
        foreach ($attributes as $key => $value) {
            try {
                $this->{$key} = $value;
            } catch (\UnexpectedValueException $exception) {
                die($exception->getMessage());
            }
        }

        return $this;
    }


    public function getId(): string {
        return $this->id;
    }

    public function getName(): string {
        return $this->name;
    }

    public function getGroup(): string {
        return $this->group;
    }
    public function getDescription(): string {
        return $this->description;
    }

    public function hasAction() {
        return   has_action($this->action);
    }

    public function getAction() {
        return $this->action;
    }

    /**
     * Execute current trigger
     */
    public function run() {
        echo 'Running trigger';
    }
}
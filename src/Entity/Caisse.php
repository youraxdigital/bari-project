<?php

namespace App\Entity;


use App\Repository\CaisseRepository;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: CaisseRepository::class)]
class Caisse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;


    //#[ORM\ManyToOne(targetEntity: User::class)]
    //private $agent;


    #[ORM\Column(type: 'datetime')]
    private $openedAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $closedAt = null;


    #[ORM\Column(type: 'float')]
    private $montantInitial;


    #[ORM\Column(type: 'float')]
    private $montantActuel;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }


    /**
     * @return mixed
     */
    public function getOpenedAt()
    {
        return $this->openedAt;
    }

    /**
     * @param mixed $openedAt
     */
    public function setOpenedAt($openedAt): void
    {
        $this->openedAt = $openedAt;
    }

    /**
     * @return mixed
     */
    public function getMontantInitial()
    {
        return $this->montantInitial;
    }

    /**
     * @param mixed $montantInitial
     */
    public function setMontantInitial($montantInitial): void
    {
        $this->montantInitial = $montantInitial;
    }

    /**
     * @return mixed
     */
    public function getMontantActuel()
    {
        return $this->montantActuel;
    }

    /**
     * @param mixed $montantActuel
     */
    public function setMontantActuel($montantActuel): void
    {
        $this->montantActuel = $montantActuel;
    }

    /**
     * @return null
     */
    public function getClosedAt()
    {
        return $this->closedAt;
    }

    /**
     * @param null $closedAt
     */
    public function setClosedAt($closedAt): void
    {
        $this->closedAt = $closedAt;
    }





}

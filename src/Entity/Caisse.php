<?php

namespace App\Entity;


use App\Repository\CaisseRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;


#[ORM\Entity(repositoryClass: CaisseRepository::class)]
class Caisse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;


    //#[ORM\ManyToOne(targetEntity: User::class)]
    //private $agent;

    #[ORM\Column(type: 'string', length: 255)]
    private string $agentResponsable;


    #[ORM\Column(type: 'datetime')]
    private $openedAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $closedAt = null;


    #[ORM\Column(type: 'float')]
    private $montantInitial;


    #[ORM\Column(type: 'float')]
    private $montantActuel;

    #[ORM\Column(type: 'float', nullable: true)]
    private $montantCloture = null;

    #[ORM\OneToMany(mappedBy: 'caisse', targetEntity: MouvementCaisse::class, orphanRemoval: true)]
    private Collection $mouvements;

    public function __construct()
    {
        $this->mouvements = new ArrayCollection();
    }

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

    /**
     * @return Collection
     */
    public function getMouvements(): Collection
    {
        return $this->mouvements;
    }

    /**
     * @param Collection $mouvements
     */
    public function setMouvements(Collection $mouvements): void
    {
        $this->mouvements = $mouvements;
    }

    /**
     * @return string
     */
    public function getAgentResponsable(): string
    {
        return $this->agentResponsable;
    }

    /**
     * @param string $agentResponsable
     */
    public function setAgentResponsable(string $agentResponsable): self
    {
        $this->agentResponsable = $agentResponsable;
        return $this;
    }

    /**
     * @return float|null
     */
    public function getMontantCloture(): ?float
    {
        return $this->montantCloture;
    }

    /**
     * @param float $montantCloture
     */
    public function setMontantCloture(float $montantCloture): self
    {
        $this->montantCloture = $montantCloture;
        return $this;
    }













}

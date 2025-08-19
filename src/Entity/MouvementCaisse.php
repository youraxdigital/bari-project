<?php

namespace App\Entity;


use App\Repository\MouvementCaisseRepository;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: MouvementCaisseRepository::class)]
class MouvementCaisse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;


    #[ORM\ManyToOne(targetEntity: Caisse::class)]
    private $caisse;


    #[ORM\Column(type: 'string')]
    private $type; // ENTREE or SORTIE


    #[ORM\Column(type: 'string')]
    private $motif;


    #[ORM\Column(type: 'float')]
    private $montant;


    #[ORM\Column(type: 'datetime')]
    private $createdAt;

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
    public function getCaisse()
    {
        return $this->caisse;
    }

    /**
     * @param mixed $caisse
     */
    public function setCaisse($caisse): void
    {
        $this->caisse = $caisse;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param mixed $type
     */
    public function setType($type): void
    {
        $this->type = $type;
    }

    /**
     * @return mixed
     */
    public function getMotif()
    {
        return $this->motif;
    }

    /**
     * @param mixed $motif
     */
    public function setMotif($motif): void
    {
        $this->motif = $motif;
    }

    /**
     * @return mixed
     */
    public function getMontant()
    {
        return $this->montant;
    }

    /**
     * @param mixed $montant
     */
    public function setMontant($montant): void
    {
        $this->montant = $montant;
    }

    /**
     * @return mixed
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * @param mixed $createdAt
     */
    public function setCreatedAt($createdAt): void
    {
        $this->createdAt = $createdAt;
    }


}

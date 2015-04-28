<?php

namespace Catrobat\AppBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;

/**
 * ProgramRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ProgramRepository extends EntityRepository
{
  public function getMostDownloadedPrograms($flavor = 'pocketcode', $limit = 20, $offset = 0)
  {
    $qb = $this->createQueryBuilder('e');
  	return $qb
  	->select('e')
    ->where($qb->expr()->eq("e.visible", $qb->expr()->literal(true)))
  	->andWhere($qb->expr()->eq("e.flavor", ":flavor"))
  	->orderBy('e.downloads', 'DESC')
    ->setParameter("flavor", $flavor)
  	->setFirstResult($offset)
  	->setMaxResults($limit)
  	->getQuery()
  	->getResult();
  }

  public function getMostViewedPrograms($flavor = 'pocketcode', $limit = 20, $offset = 0)
  {
    $qb = $this->createQueryBuilder('e');
    return $qb
    ->select('e')
    ->where($qb->expr()->eq("e.visible", $qb->expr()->literal(true)))
    ->andWhere($qb->expr()->eq("e.flavor", ":flavor"))
    ->orderBy('e.views', 'DESC')
    ->setParameter("flavor", $flavor)
    ->setFirstResult($offset)
    ->setMaxResults($limit)
    ->getQuery()
    ->getResult();
  }
  
  public function getRecentPrograms($flavor = 'pocketcode', $limit = 20, $offset = 0)
  {
    $qb = $this->createQueryBuilder('e');
    return $qb
    ->select('e')
    ->where($qb->expr()->eq("e.visible", $qb->expr()->literal(true)))
    ->andWhere($qb->expr()->eq("e.flavor", ":flavor"))
    ->orderBy('e.uploaded_at', 'DESC')
    ->setParameter("flavor", $flavor)
    ->setFirstResult($offset)
    ->setMaxResults($limit)
    ->getQuery()
    ->getResult();
  }

  public function search($query, $limit=10, $offset=0)
  {
    $dql = "SELECT e,
          (CASE
            WHEN (e.name LIKE :searchterm) THEN 10
            ELSE 0
          END) +
          (CASE
            WHEN (f.username LIKE :searchterm) THEN 1
            ELSE 0
          END) +
          (CASE
            WHEN (e.description LIKE :searchterm) THEN 3
            ELSE 0
          END) +
          (CASE
            WHEN (e.id = :searchtermint) THEN 11
            ELSE 0
          END)
          AS weight
        FROM Catrobat\AppBundle\Entity\Program e
        LEFT JOIN e.user f
        WHERE
          (e.name LIKE :searchterm OR
          f.username LIKE :searchterm OR
          e.description LIKE :searchterm OR
          e.id = :searchtermint) AND 
          e.visible = true
        ORDER BY weight DESC, e.uploaded_at DESC
      ";
    $qb_program = $this->createQueryBuilder('e');
    $q2 = $qb_program->getEntityManager()->createQuery($dql);
    $q2->setFirstResult($offset);
    $q2->setMaxResults($limit);
    $q2->setParameter("searchterm", "%".$query."%");
    $q2->setParameter("searchtermint", intval($query));
    $result = $q2->getResult();
    return array_map(function($element){return $element[0];}, $result);
  }

  public function searchCount($query)
  {
    $qb_program = $this->createQueryBuilder('e');
    $dql = "SELECT COUNT(e.id)
        FROM Catrobat\AppBundle\Entity\Program e
        LEFT JOIN e.user f
        WHERE
          (e.name LIKE :searchterm OR
          f.username LIKE :searchterm OR
          e.description LIKE :searchterm OR
          e.id = :searchtermint) AND
          e.visible = true
      ";
    $q2 = $qb_program->getEntityManager()->createQuery($dql);
    $q2->setParameter("searchterm", "%".$query."%");
    $q2->setParameter("searchtermint", intval($query));
    $result = $q2->getSingleScalarResult();
    return $result;
  }

  public function getUserPrograms($user_id)
  {
    $qb = $this->createQueryBuilder('e');
    return $qb
    ->select('e')
    ->leftJoin('e.user', 'f')
    ->where($qb->expr()->eq("e.visible", $qb->expr()->literal(true)))
    ->andWhere($qb->expr()->eq("f.id", ":user_id"))
    ->setParameter("user_id", $user_id)
    ->getQuery()
    ->getResult();
  }

  public function getTotalPrograms($flavor = 'pocketcode')
  {
      $qb = $this->createQueryBuilder('e');
      return $qb
          ->select('COUNT (e.id)')
          ->where($qb->expr()->eq("e.visible", $qb->expr()->literal(true)))
          ->andWhere($qb->expr()->eq("e.flavor", ":flavor"))
          ->setParameter("flavor", $flavor)
          ->getQuery()
          ->getSingleScalarResult();
  }
}

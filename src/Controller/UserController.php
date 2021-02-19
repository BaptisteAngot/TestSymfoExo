<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class UserController extends AbstractController
{
    /**
     * @Route("/createUser", name="createUser", methods={"POST"})
     * @param Request $request
     * @return Response
     */
    public function createUser(Request $request): Response
    {
        $user = new User();
        $datas = json_decode($request->getContent(),true);
        $response = new Response();
        $entityManager = $this->getDoctrine()->getManager();
        $error = [];
        if ($datas['name']){
            $user->setName($datas['name']);
        }else {
            array_push($error,"No name");
            $response->setContent("Mauvais format de la requête");
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
        }
        if (empty($error)) {
            $entityManager->persist($user);
            $entityManager->flush();
            $response->setStatusCode(Response::HTTP_OK);
            $response->setContent(json_encode($user));
        }else {
            $response->setContent(json_encode($error));
        }
        return $response;
    }

    /**
     * @Route("/user", name="user", methods={"GET"})
     * @param Request $request
     * @param UserRepository $userRepository
     * @return Response
     */
    public function getAlluser(Request $request,UserRepository $userRepository)
    {
        $filter = [];
        $em = $this->getDoctrine()->getManager();
        $metaData = $em->getClassMetadata(User::class)->getFieldNames();
        foreach ($metaData as $value) {
            if ($request->query->get($value)) {
                $filter[$value] = $request->query->get($value);
            }
        }

        $response = new JsonResponse();
        $response->setStatusCode(Response::HTTP_OK);
        $response->setContent($this->serializeUser($userRepository->findBy($filter)));
        return $response;
    }

    private function serializeUser($objet){
        $defaultContext = [
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object, $format, $context) {
                return $object->getId();
            },
        ];
        $normalizer = new ObjectNormalizer(null, null, null, null, null, null, $defaultContext);
        $serializer = new Serializer([$normalizer], [new JsonEncoder()]);

        return $serializer->serialize($objet, 'json');
    }

    /**
     * @Route("user",name="deleteUser",methods={"DELETE"})
     * @param Request $request
     * @param UserRepository $userRepository
     * @return Response
     */
    public function deletePost(Request $request, UserRepository $userRepository): Response
    {
        $entityManger = $this->getDoctrine()->getManager();
        $datas = json_decode(
            $request->getContent(),
            true
        );
        $response = new Response();
        if (isset($datas['userId']))
        {
            $post = $userRepository->find($datas['userId']);
            if ($post === null) {
                $response->setContent("Cet utilisateur n'existe pas");
                $response->setStatusCode(Response::HTTP_FORBIDDEN);
            }else {
                $entityManger->remove($post);
                $entityManger->flush();
                $response->setContent("Suppression du user");
                $response->setStatusCode(Response::HTTP_OK);
            }
        }else {
            $response->setContent("Mauvais format de la requête");
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
        }
        return $response;
    }
}

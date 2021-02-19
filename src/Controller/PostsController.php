<?php

namespace App\Controller;

use App\Entity\Posts;
use App\Entity\User;
use App\Repository\PostsRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

class PostsController extends AbstractController
{
    /**
     * @Route("api/posts", name="posts", methods={"GET"})
     * @param Request $request
     * @param PostsRepository $postsRepository
     * @return Response
     */
    public function getPosts(Request $request, PostsRepository $postsRepository): Response
    {
        $filter = [];
        $em = $this->getDoctrine()->getManager();
        $metaData = $em->getClassMetadata(Posts::class)->getFieldNames();
        foreach ($metaData as $value) {
            if ($request->query->get($value)) {
                $filter[$value] = $request->query->get($value);
            }
        }

        $response = new JsonResponse();
        $response->setStatusCode(Response::HTTP_OK);
        $response->setContent($this->serializePosts($postsRepository->findBy($filter)));
        return $response;
    }

    /**
     * @Route("/posts",name="frontPosts",methods={"GET"})
     * @param Request $request
     * @param PostsRepository $postsRepository
     * @return Response
     */
    public function getPostsFront(Request $request, PostsRepository $postsRepository): Response
    {
        $filter = [];
        $em = $this->getDoctrine()->getManager();
        $metaData = $em->getClassMetadata(Posts::class)->getFieldNames();
        foreach ($metaData as $value) {
            if ($request->query->get($value)) {
                $filter[$value] = $request->query->get($value);
            }
        }
        return $this->render('posts/index.html.twig', [
            'Posts' => $postsRepository->findBy($filter)
        ]);
    }

    /**
     * @Route("api/posts",name="createPost",methods={"POST"})
     * @param Request $request
     * @param UserRepository $userRepository
     * @return Response
     */
    public function createPost(Request $request, UserRepository $userRepository)
    {
        $entityManager = $this->getDoctrine()->getManager();
        $post = new Posts();
        $response = new Response();
        $error = [];
        $datas = json_decode($request->getContent(),true);
        if (!$datas['title']){
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            array_push($error, "No title");
        }else {
            $post->setTitle($datas['title']);
        }
        if (!$datas['body']){
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            array_push($error, "No body");
        }else {
            $post->setBody($datas['body']);
        }
        if (!$datas['user']){
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
            array_push($error, "No user");
        }else {
            $user = $userRepository->findOneBy(['id'=>$datas['user']]);
            if ($user !== null) {
                $post->setUserId($user);
            }else{
                $response->setStatusCode(Response::HTTP_BAD_REQUEST);
                array_push($error, "This user doesn't exist");
            }
        }
        $post->setDate(new \DateTime());
        if (empty($error)) {
            $entityManager->persist($post);
            $entityManager->flush();
            $response->setStatusCode(Response::HTTP_OK);
            $response->setContent(json_encode($post));
        }else{
            $response->setContent(json_encode($error));
        }
        return $response;
    }

    /**
     * @Route("api/posts",name="deletePost",methods={"DELETE"})
     * @param Request $request
     * @param PostsRepository $postsRepository
     * @return Response
     */
    public function deletePost(Request $request, PostsRepository $postsRepository): Response
    {
        $entityManger = $this->getDoctrine()->getManager();
        $datas = json_decode(
            $request->getContent(),
            true
        );
        $response = new Response();
        if (isset($datas['postId']))
        {
            $post = $postsRepository->find($datas['postId']);
            if ($post === null) {
                $response->setContent("Ce post n'existe pas");
                $response->setStatusCode(Response::HTTP_FORBIDDEN);
            }else {
                $entityManger->remove($post);
                $entityManger->flush();
                $response->setContent("Suppression du post");
                $response->setStatusCode(Response::HTTP_OK);
            }
        }else {
            $response->setContent("Mauvais format de la requête");
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
        }
        return $response;
    }

    /**
     * @Route("api/posts",name="updatePost",methods={"PATCH"})
     * @param Request $request
     * @param PostsRepository $postsRepository
     * @return Response
     */
    public function updatePost(Request $request, PostsRepository $postsRepository)
    {
        $entityManager = $this->getDoctrine()->getManager();
        $datas = json_decode(
            $request->getContent(),
            true
        );
        $response = new Response();
        if (isset($datas['postId']))
        {
            $post = $postsRepository->find($datas['postId']);
            $newPost = $post;
            isset($datas['title']) && $newPost->setTitle($datas['title']);
            isset($datas['body']) && $newPost->setBody($datas['body']);
            $newPost->setDate(new \DateTime());

            $entityManager->persist($post);
            $entityManager->flush();
            $response->setContent("Mise à jours du post à l'id : " . $post->getId());
            $response->setStatusCode(Response::HTTP_OK);
        }else {
            $response->setContent("Mauvais format de la requête");
            $response->setStatusCode(Response::HTTP_BAD_REQUEST);
        }
        return $response;
    }



    private function serializePosts($objet){
        $defaultContext = [
            AbstractNormalizer::CIRCULAR_REFERENCE_HANDLER => function ($object, $format, $context) {
                return $object->getId();
            },
        ];
        $normalizer = new ObjectNormalizer(null, null, null, null, null, null, $defaultContext);
        $serializer = new Serializer([$normalizer], [new JsonEncoder()]);

        return $serializer->serialize($objet, 'json');
    }
}

<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Contact;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use App\Form\ContactFormType;
use App\Entity\Category;
use Symfony\Component\String\Slugger\SluggerInterface;
use App\Entity\Post;
use App\Form\PostFormType;
use App\Entity\Comment;
use App\Form\CommentFormType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;


final class PageController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(ManagerRegistry $doctrine, Request $request): Response
    {
        $repository = $doctrine->getRepository(Category::class);

        $categories = $repository->findAll();

        return $this->render('index.html.twig', ['categories' => $categories]);
    }


    #[Route('/about', name: 'about')]
    public function about(): Response
    {
        return $this->render('about.html.twig', []);
    }

    #[Route('/blog', name: 'blog')]
    public function blog(): Response
    {
        return $this->render('blog.html.twig', []);
    }

    #[Route('/contact', name: 'contact')]
    public function contact(ManagerRegistry $doctrine, Request $request): Response
    {
        $contact = new Contact();
        $form = $this->createForm(ContactFormType::class, $contact);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $contacto = $form->getData();
            $entityManager = $doctrine->getManager();
            $entityManager->persist($contacto);
            $entityManager->flush();
            return $this->redirectToRoute('thankyou', []);
        }
        return $this->render('contact.html.twig', array(
            'form' => $form->createView()
        ));
    }

    #[Route('/single_post', name: 'single_post')]
    public function single_post(): Response
    {
        return $this->render('single_post.html.twig', []);
    }

    #[Route('/thankyou', name: 'thankyou')]
    public function thankyou(): Response
    {
        return $this->render('thankyou.html.twig', []);
    }

    #[Route('/blog/new', name: 'new_post')]
    public function newPost(ManagerRegistry $doctrine, Request $request, SluggerInterface $slugger): Response
    {
        $post = new Post();
        $form = $this->createForm(PostFormType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $post = $form->getData();

            // Quitamos los caracteres especiales del título para crear el slug
            $post->setSlug($slugger->slug($post->getTitle()));

            // Procesar la subida de la imagen
            $imageFile = $form->get('image')->getData(); // Asegúrate de que 'image' coincida con el campo de tu PostFormType
            if ($imageFile) {
                $newFilename = uniqid() . '.' . $imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/images/blog',
                        $newFilename
                    );
                } catch (FileException $e) {
                }

                $post->setImage($newFilename);
            }

            // Guardamos el usuario que crea el post y los contadores a 0
            $post->setPostUser($this->getUser());
            $post->setNumLikes(0);
            $post->setNumComments(0);

            // Si no tienes un campo de fecha automática en la entidad, puedes establecerla aquí:
            // $post->setPublishedAt(new \DateTime());

            $entityManager = $doctrine->getManager();
            $entityManager->persist($post);
            $entityManager->flush();

            return $this->redirectToRoute('single_post', ["slug" => $post->getSlug()]);
        }

        return $this->render('blog/new_post.html.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/single_post/{slug}', name: 'single_post')]
    public function post(ManagerRegistry $doctrine, Request $request, $slug): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $post = $repository->findOneBy(["slug" => $slug]);
        $recents = $repository->findRecents();

        $commentRepository = $doctrine->getRepository(Comment::class);
        $recentComments = $commentRepository->findBy([], ['publishedAt' => 'DESC'], 5);

        $comment = new Comment();
        $form = $this->createForm(CommentFormType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $comment = $form->getData();
            $comment->setPost($post);
            $post->setNumComments($post->getNumComments() + 1);
            $entityManager = $doctrine->getManager();
            $entityManager->persist($comment);
            $entityManager->flush();
            return $this->redirectToRoute('single_post', ["slug" => $post->getSlug()]);
        }

        return $this->render('blog/single_post.html.twig', [
            'post' => $post,
            'recents' => $recents,
            'recentComments' => $recentComments,
            'commentForm' => $form->createView()
        ]);
    }

    private const POSTS_PER_PAGE = 1;

    #[Route('/blog/{page}', name: 'blog', requirements: ['page' => '\d+'])]
    public function blogPage(ManagerRegistry $doctrine, int $page = 1): Response
    {
        $page = max(1, $page);

        $repository = $doctrine->getRepository(Post::class);
        //Aquí obtenemos los datos con el método creado en el repositorio
        $posts = $repository->findPaginados($page, self::POSTS_PER_PAGE);
        $recents = $repository->findRecents();

        $total = count($posts);
        $totalPages = max(1, (int) ceil($total / self::POSTS_PER_PAGE));

        // Si piden una página que no existe, redirigimos a la última
        if ($page > $totalPages) {
            return $this->redirectToRoute('blog', ['page' => $totalPages]);
        }

        return $this->render('blog.html.twig', [
            'posts' => $posts,
            'recents' => $recents,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
        ]);
    }

    #[Route('/blog/buscar/{page}', name: 'blog_buscar')]
    public function buscar(ManagerRegistry $doctrine, Request $request, int $page = 1): Response
    {
        $page = max(1, $page);

        $repository = $doctrine->getRepository(Post::class);
        $searchTerm = $request->query->get('searchTerm', '');
        $posts = $repository->findBuscadosPaginados($searchTerm, $page, self::POSTS_PER_PAGE);
        $recents = $repository->findRecents();

        $total = count($posts);
        $totalPages = max(1, (int) ceil($total / self::POSTS_PER_PAGE));

        // Si piden una página que no existe, redirigimos a la última (conservando la búsqueda)
        if ($page > $totalPages) {
            return $this->redirectToRoute('blog_buscar', ['page' => $totalPages, 'searchTerm' => $searchTerm]);
        }

        return $this->render('blog.html.twig', [
            'posts' => $posts,
            'recents' => $recents,
            'searchTerm' => $searchTerm,
            'page' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
        ]);
    }

    #[Route('/single_post/{slug}/like', name: 'post_like')]
    public function like(ManagerRegistry $doctrine, $slug): JsonResponse
    {
        $repository = $doctrine->getRepository(Post::class);
        $post = $repository->findOneBy(["slug" => $slug]);
        if ($post) {
            $post->like();
            $entityManager = $doctrine->getManager();
            $entityManager->persist($post);
            $entityManager->flush();
        }
        $data = ['success' => true, 'likes' => $post->getNumLikes()];
        return $this->json($data);

    }

    #[Route('/blog/buscar', name: 'blog_buscar_post')]
    public function buscarPost(ManagerRegistry $doctrine, Request $request): Response
    {
        $repository = $doctrine->getRepository(Post::class);
        $searchTerm = $request->query->get('searchTerm', '');
        $posts = $repository->findByText($searchTerm);
        $recents = $repository->findRecents();
        return $this->render('blog.html.twig', [
            'posts' => $posts,
            'recents' => $recents,
            'searchTerm' => $searchTerm
        ]);
    }


}

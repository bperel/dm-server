<?php

namespace App\Controller;

use App\Entity\Coa\InducksPublication;
use App\Entity\Dm\Users;
use App\Entity\Dm\UsersSuggestionsNotifications;
use App\Entity\DmStats\UtilisateursPublicationsSuggerees;
use App\Service\NotificationService;
use DateTime;
use Doctrine\Common\Collections\Criteria;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NotificationController extends AbstractController implements RequiresDmVersionController
{
    /**
     * @Route(
     *     methods={"POST"},
     *     path="/notification/send"
     * )
     */
    public function sendNotification(LoggerInterface $logger, NotificationService $notificationService) : Response
    {
        $notificationsSent = 0;
        $issueNotificationsToSend = [];
        $userIdsToNotify = [];
        try {
            $recently = new DateTime('-7 days midnight');
            $today = new DateTime('today midnight');

            /** @var UtilisateursPublicationsSuggerees[] $suggestedIssuesAllUsers */
            $suggestedIssuesAllUsers = $this->getEm('dm_stats')->getRepository(UtilisateursPublicationsSuggerees::class)
                ->matching((new Criteria())->where(Criteria::expr()->andX(
                    Criteria::expr()->gte('oldestdate', $recently),
                    Criteria::expr()->lt('oldestdate', $today)
                )))->getValues();

            $logger->info(count($suggestedIssuesAllUsers).' potential notifications from suggested issues released recently');

            $userNotificationCountriesQb = ($this->getEm('dm')->createQueryBuilder())
                ->select('u AS user')
                ->addSelect('GROUP_CONCAT(uo.optionValeur) AS countries')
                ->from(Users::class, 'u')
                ->leftJoin('u.options', 'uo')
                ->where('uo.optionNom = :option_name')
                ->setParameter(':option_name', 'suggestion_notification_country')
                ->groupBy('u.id');

            $usersAndNotificationCountries = $userNotificationCountriesQb->getQuery()->getResult();

            /** @var Users $user */
            foreach($usersAndNotificationCountries as ['user' => $user, 'countries' => $notificationCountries]) {
                $notificationCountriesForUser = explode(',', $notificationCountries);
                $suggestedIssuesForUser = $this->getSuggestedIssuesToNotifyUser($suggestedIssuesAllUsers, $user->getId(), $notificationCountriesForUser, $logger);

                foreach($suggestedIssuesForUser as $suggestedIssueForUser) {
                    if (!isset($issueNotificationsToSend[$suggestedIssueForUser])) {
                        $issueNotificationsToSend[$suggestedIssueForUser][] = $user;
                    }
                }
            }

            foreach($issueNotificationsToSend as $issue => $usersToNotify) {
                /** @var InducksPublication $publication */
                $publication = $this->getEm('coa')->getRepository(InducksPublication::class)->find(json_decode($issue)->publicationcode);

                $issueCode = json_decode($issue)->publicationcode.' '.json_decode($issue)->issuenumber;
                $title = $publication->getTitle().' '.json_decode($issue)->issuenumber;
                $notificationsSent += $notificationService->sendSuggestedIssueNotification($issueCode, $title, $usersToNotify);
            }
        } catch (Exception $e) {
            $logger->error($e->getMessage());
            return new Response('Internal server error', Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return new JsonResponse(['notifications_sent' => $notificationsSent], Response::HTTP_ACCEPTED);
    }

    /**
     * @param UtilisateursPublicationsSuggerees[] $suggestedIssuesToNotify
     * @param int $userId
     * @param string[] $notificationCountriesForUser
     * @param LoggerInterface $logger
     * @return UtilisateursPublicationsSuggerees[]
     */
    private function getSuggestedIssuesToNotifyUser(array $suggestedIssuesToNotify, int $userId, array $notificationCountriesForUser, LoggerInterface $logger): array
    {
        $suggestionsToNotifyForUser = [];
        foreach ($suggestedIssuesToNotify as $key => $suggestedIssue) {
            $suggestedIssueCountryCode = explode('/', $suggestedIssue->getPublicationcode())[0];
            $suggestedIssueCode = "{$suggestedIssue->getPublicationcode()} {$suggestedIssue->getIssuenumber()}";
            if ($suggestedIssue->getIdUser() !== $userId) {
                continue;
            }
            if (!in_array($suggestedIssueCountryCode, $notificationCountriesForUser, true)) {
                $logger->info("User $userId doesn't want to be notified for releases of country $suggestedIssueCountryCode");
                continue;
            }

            $alreadySentNotificationQb = $this->getEm('dm')->createQueryBuilder();
            $alreadySentNotificationQb
                ->select('existingNotification')
                ->from(UsersSuggestionsNotifications::class, 'existingNotification')
                ->innerJoin('existingNotification.user', 'u')
                ->where('u.id = :userId')
                ->setParameter(':userId', $userId)
                ->andWhere('existingNotification.issuecode = :issueCode')
                ->setParameter(':issueCode', $suggestedIssueCode);

            if (!empty($alreadySentNotificationQb->getQuery()->getResult())) {
                $logger->info("A notification has already been sent to user $userId concerning the release of issue $suggestedIssueCode");
                continue;
            }

            $suggestionsToNotifyForUser[] = json_encode([
                'publicationcode' => $suggestedIssue->getPublicationcode(),
                'issuenumber' => $suggestedIssue->getIssuenumber()
            ]);
        }
        return $suggestionsToNotifyForUser;
    }
}

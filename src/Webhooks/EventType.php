<?php

declare(strict_types=1);

namespace Reshapify\SendSeven\Webhooks;

/**
 * Every webhook event SendSeven sends (docs.sendseven.com, Webhook Events
 * Reference). Use these when registering an endpoint.
 */
enum EventType: string
{
    case MessageReceived = 'message.received';
    case MessageSent = 'message.sent';
    case MessageDelivered = 'message.delivered';
    case MessageRead = 'message.read';
    case MessageFailed = 'message.failed';
    case MessageReaction = 'message.reaction';
    case ConversationCreated = 'conversation.created';
    case ConversationClosed = 'conversation.closed';
    case ConversationAssigned = 'conversation.assigned';
    case ConversationReopened = 'conversation.reopened';
    case ConversationUpdated = 'conversation.updated';
    case ConversationTranscriptCreated = 'conversation.transcript.created';
    case ContactCreated = 'contact.created';
    case ContactUpdated = 'contact.updated';
    case ContactDeleted = 'contact.deleted';
    /** A contact joined a list (newsletter subscription), not a browser push subscription. */
    case ContactSubscribed = 'contact.subscribed';
    case ContactUnsubscribed = 'contact.unsubscribed';
    case CampaignMessageSent = 'campaign.message.sent';
    case CampaignMessageDelivered = 'campaign.message.delivered';
    case CampaignMessageRead = 'campaign.message.read';
    case CampaignMessageFailed = 'campaign.message.failed';
    case CampaignEmailSent = 'campaign.email.sent';
    case CampaignEmailDelivered = 'campaign.email.delivered';
    case CampaignEmailBounced = 'campaign.email.bounced';
    case CampaignEmailOpened = 'campaign.email.opened';
    case CampaignEmailComplained = 'campaign.email.complained';
    case ChannelCreated = 'channel.created';
    case ChannelUpdated = 'channel.updated';
    case ChannelDeleted = 'channel.deleted';
    case CommentReceived = 'comment.received';
    case CommentUpdated = 'comment.updated';
    case CommentDeleted = 'comment.deleted';
    case PostCreated = 'post.created';
    case PostUpdated = 'post.updated';
    case PostDeleted = 'post.deleted';
    case TeamChatMessageCreated = 'team_chat.message.created';
    case LinkClicked = 'link.clicked';
    case EmailReceived = 'email.received';
    case EmailSent = 'email.sent';
    case EmailDelivered = 'email.delivered';
    case EmailBounced = 'email.bounced';
    case EmailOpened = 'email.opened';
    case EmailComplained = 'email.complained';

    /**
     * The events a messaging integration usually needs: inbound messages and
     * delivery updates.
     *
     * @return list<self>
     */
    public static function messaging(): array
    {
        return [self::MessageReceived, self::MessageSent, self::MessageDelivered, self::MessageRead, self::MessageFailed];
    }
}

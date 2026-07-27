<?php

namespace App\Services\Announcements;

use App\DTOs\AnnouncementDTO;
use App\Events\Announcements\AnnouncementCreated;
use App\Events\Announcements\AnnouncementDeleted;
use App\Events\Announcements\AnnouncementUpdated;
use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use App\Models\Notification;
use App\Repositories\Announcements\AnnouncementRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AnnouncementService
{
    private const EAGER_LOAD = ['creator.employee', 'attachments', 'plantillaPostings'];

    public function __construct(
        private readonly AnnouncementRepository $announcements,
    ) {
    }

    public function authorizeOwnerOrSuperAdmin(Request $request, Announcement $announcement): void
    {
        $user = $request->user();

        if ($announcement->created_by === $user->id || $user->can('announcements.manage.all')) {
            return;
        }

        abort(403, 'You can only manage your own announcements.');
    }

    public function create(AnnouncementDTO $data, Request $request): Announcement
    {
        $announcement = Announcement::create([
            ...$data->toModelAttributes(),
            'created_by' => $request->user()->id,
        ]);

        $announcement->plantillaPostings()->sync($data->plantillaPostingIds);
        $this->storeAttachments($request, $announcement);

        $fresh = $announcement->fresh(self::EAGER_LOAD);

        broadcast(new AnnouncementCreated($fresh))->toOthers();

        Notification::create([
            'type'       => 'announcement',
            'title'      => 'New announcement',
            'message'    => $fresh->title,
            'data'       => [
                'announcement_id'    => $fresh->id,
                'priority'           => $fresh->priority,
                'category'           => $fresh->category,
                'posted_by_user_id'  => $fresh->created_by,
            ],
            'visibility' => 'all',
        ]);

        return $fresh;
    }

    public function update(int $id, AnnouncementDTO $data, Request $request): Announcement
    {
        $announcement = $this->announcements->findOrFail($id);
        $this->authorizeOwnerOrSuperAdmin($request, $announcement);

        $announcement->update($data->toModelAttributes());
        $announcement->plantillaPostings()->sync($data->plantillaPostingIds);
        $this->storeAttachments($request, $announcement);

        $fresh = $announcement->fresh(self::EAGER_LOAD);

        broadcast(new AnnouncementUpdated($fresh))->toOthers();

        return $fresh;
    }

    public function delete(int $id, Request $request): void
    {
        $announcement = $this->announcements->findOrFail($id);
        $this->authorizeOwnerOrSuperAdmin($request, $announcement);

        $announcementId = $announcement->id;
        $announcement->delete();

        broadcast(new AnnouncementDeleted($announcementId))->toOthers();
    }

    public function togglePin(int $id, Request $request): Announcement
    {
        $announcement = $this->announcements->findOrFail($id);
        $this->authorizeOwnerOrSuperAdmin($request, $announcement);

        $announcement->update(['is_pinned' => ! $announcement->is_pinned]);

        broadcast(new AnnouncementUpdated($announcement->fresh(self::EAGER_LOAD)))->toOthers();

        return $announcement;
    }

    public function toggleArchive(int $id, Request $request): Announcement
    {
        $announcement = $this->announcements->findOrFail($id);
        $this->authorizeOwnerOrSuperAdmin($request, $announcement);

        $announcement->update(['is_archived' => ! $announcement->is_archived]);

        broadcast(new AnnouncementUpdated($announcement->fresh(self::EAGER_LOAD)))->toOthers();

        return $announcement;
    }

    private function storeAttachments(Request $request, Announcement $announcement): void
    {
        if (! $request->hasFile('attachments')) {
            return;
        }

        foreach ($request->file('attachments') as $file) {
            $path = $file->store("announcements/{$announcement->id}", 'local');

            AnnouncementAttachment::create([
                'announcement_id'   => $announcement->id,
                'original_filename' => $file->getClientOriginalName(),
                'stored_path'       => $path,
                'mime_type'         => $file->getClientMimeType(),
                'size'              => $file->getSize(),
            ]);
        }
    }

    public function downloadAttachment(int $attachmentId): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $attachment = AnnouncementAttachment::findOrFail($attachmentId);
        $attachment->increment('downloads_count');

        return Storage::disk('local')->download(
            $attachment->stored_path,
            $attachment->original_filename
        );
    }

    public function deleteAttachment(int $attachmentId, Request $request): void
    {
        $attachment = AnnouncementAttachment::with('announcement')->findOrFail($attachmentId);
        $this->authorizeOwnerOrSuperAdmin($request, $attachment->announcement);

        Storage::disk('local')->delete($attachment->stored_path);
        $attachment->delete();
    }
}

# KITEL Publishing Implementation Checklist

## A. Preflight
- [ ] Read IA/P0/P1/P2/P3/P4
- [ ] Inspect git status
- [ ] Confirm working branch
- [ ] Identify Rhymix root
- [ ] Confirm current site boots
- [ ] Identify layout and skin entrypoints
- [ ] Confirm Pretendard loading
- [ ] Record current permission-sensitive pages

## B. Shared foundation
- [ ] Common color tokens
- [ ] Typography tokens
- [ ] Radius/shadow tokens
- [ ] 1160px main container
- [ ] Shared buttons
- [ ] Shared inputs
- [ ] Search primitive
- [ ] Pagination primitive
- [ ] Media slot primitive

## C. Global layout
- [ ] Header
- [ ] Active nav
- [ ] MegaMenu
- [ ] Search entry
- [ ] Logged-out actions
- [ ] Logged-in actions
- [ ] Footer
- [ ] Support label replaces Else
- [ ] Mobile global navigation

## D. Generic board
- [ ] List
- [ ] Detail
- [ ] Write
- [ ] Attachments
- [ ] Comment composer
- [ ] Comment list/replies
- [ ] Search
- [ ] Pagination
- [ ] Empty
- [ ] Search empty
- [ ] Permission denied
- [ ] News locked body

## E. Board variants
- [ ] News x3
- [ ] Seminar
- [ ] Sharing community list
- [ ] Exhibition gallery
- [ ] Suggestions inline composer/list

## F. Calendar
- [ ] Month grid
- [ ] Today
- [ ] Events
- [ ] Empty month
- [ ] Prev/next
- [ ] Admin controls
- [ ] Admin event form/list
- [ ] Week view NOT faked if backend absent

## G. Archive
- [ ] Breadcrumb
- [ ] Folder rows
- [ ] File rows
- [ ] Empty folder
- [ ] Folder create
- [ ] Upload
- [ ] Download
- [ ] Delete permissions

## H. Homework
- [ ] Task list
- [ ] My submission status
- [ ] Task detail
- [ ] Submit
- [ ] Resubmit
- [ ] Late state
- [ ] Full submissions for authorized members
- [ ] Junior privacy
- [ ] Tech-dept task management
- [ ] Tech-dept summary
- [ ] Existing matrix preserved

## I. Exhibition
- [ ] Approved gallery
- [ ] Pending state
- [ ] No-image fallback
- [ ] Write fixed fields
- [ ] Approval behavior
- [ ] No comments in final UX

## J. Suggestions
- [ ] Inline composer
- [ ] Own-list scope
- [ ] Staff-list scope
- [ ] Anonymous option
- [ ] No incorrect public/private selector
- [ ] Response status if backend supplies it

## K. Home
- [ ] Hero live widget
- [ ] Hero image replaceable
- [ ] Single hero baseline
- [ ] Upcoming events live widget
- [ ] News preview from live data
- [ ] Exhibition preview from live data
- [ ] Placeholder-only sections not mistaken for backend features

## L. Account
- [ ] Login core action preserved
- [ ] Login error state
- [ ] Signup current fields inspected
- [ ] Signup styling
- [ ] Signup complete approval-aware copy
- [ ] My Page
- [ ] Password change safe
- [ ] Recent posts

## M. Responsive
- [ ] 390
- [ ] 768
- [ ] 1024
- [ ] 1440
- [ ] 1920
- [ ] GNB mobile replacement
- [ ] Board mobile reflow
- [ ] Forms fluid
- [ ] Cards reflow
- [ ] Footer reflow
- [ ] Touch targets appropriate

## N. Regression
- [ ] Auth
- [ ] Board CRUD
- [ ] Search/pagination
- [ ] Comments
- [ ] News grants
- [ ] Calendar CRUD
- [ ] Archive operations
- [ ] Homework permissions
- [ ] Homework matrix
- [ ] Exhibition approval
- [ ] Suggestions privacy
- [ ] Home widgets

## O. Final hygiene
- [ ] No temporary Figma asset URLs
- [ ] No unnecessary Tailwind/React dependency
- [ ] No hidden schema changes
- [ ] No permission regression
- [ ] IMPLEMENTATION_NOTES.md written
- [ ] Representative screenshots captured
